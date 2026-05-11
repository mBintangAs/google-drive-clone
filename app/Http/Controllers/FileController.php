<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToFavouritesRequest;
use App\Http\Requests\CreateFolderRequest;
use App\Http\Requests\FileActionsRequest;
use App\Http\Requests\MoveFileRequest;
use App\Http\Requests\RenameFileRequest;
use App\Http\Requests\ShareFilesRequest;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\TrashFileRequest;
use App\Http\Resources\FileResource;
use App\Mail\ShareFilesMail;
use App\Models\File;
use App\Models\FileShare;
use App\Models\StarredFile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class FileController extends Controller
{
    /**
     * Display the My Files page.
     *
     * @return \Inertia\Inertia
     */
    public function index(?string $folder = null)
    {
        $search = request()->search;

        if ($folder) {
            $folder = File::query()
                ->where([
                    'created_by' => auth()->id(),
                    'path' => $folder,
                ])
                ->firstOrFail();
        }
        if (! $folder) {
            $folder = $this->getRoot();
        }

        $favourites = request()->exists('favourites');
        $query = File::select('files.*')
            ->where('created_by', auth()->id())
            ->orderBy('is_folder', 'DESC')
            ->orderBy('files.created_at', 'DESC')
            ->orderBy('files.id', 'DESC')
            ->with(['starred:id,user_id,file_id,created_at']);
        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        } else {
            $query->where('parent_id', $folder->id);
        }
        if ($favourites) {
            $query->join('starred_files', 'starred_files.file_id', '=', 'files.id')
                ->where('starred_files.user_id', auth()->id());
        }
        $files = $query->paginate(10);

        $files = FileResource::collection($files);

        if (request()->wantsJson()) {
            return $files;
        }

        $ancestors = FileResource::collection([...$folder->ancestors, $folder]);

        $folder = new FileResource($folder);

        return Inertia::render('MyFiles', [
            'rootFolder' => $folder,
            'files' => $files,
            'ancestors' => $ancestors,
            'favourites' => $favourites,
            'search' => $search,
        ]);
    }

    /**
     * Display the trash page.
     *
     * @return \Inertia\Inertia
     */
    public function trash()
    {
        $search = request()->search;
        $query = File::onlyTrashed()
            ->where('created_by', auth()->id())
            ->orderBy('is_folder', 'DESC')
            ->orderBy('deleted_at', 'DESC');

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $files = $query->paginate(10);

        $files = FileResource::collection($files);

        if (request()->wantsJson()) {
            return $files;
        }

        return Inertia::render('Trash', [
            'files' => $files,
            'search' => $search,
        ]);
    }

    /**
     * Create the folder with the provided details.
     *
     * @return void
     */
    public function createFolder(CreateFolderRequest $request)
    {
        $payload = $request->validated();
        $parent = $request->parent;
        if (! $parent) {
            $parent = $this->getRoot();
        }

        $file = new File();
        $file->is_folder = true;
        $file->name = $payload['name'];

        $parent->appendNode($file);
    }

    /**
     * Store the uploaded files.
     */
    public function storeFiles(StoreFileRequest $request)
    {
        $payload = $request->validated();
        $fileTree = $request->file_tree;
        $parent = $request->parent;
        $user = $request->user();

        if (! $parent) {
            $parent = $this->getRoot();
        }

        if (! empty($fileTree)) {
            $this->saveFileTree($fileTree, $parent, $user);
        } else {
            foreach ($payload['files'] as $file) {
                $this->saveFile($file, $parent, $user);
            }
        }
    }

    /**
     * Get the root folder of the authenticated user's id.
     *
     * @return \App\Models\File|null
     */
    private function getRoot()
    {
        $root = File::query()
            ->where('created_by', auth()->id())
            ->whereIsRoot()
            ->first();

        if ($root) {
            return $root;
        }

        $user = auth()->user();

        $root = new File();
        $root->is_folder = true;
        $root->name = $user?->email ?? 'My Files';
        $root->makeRoot()->save();

        return $root;
    }

    /**
     * Save the file tree.
     *
     * @param  array  $tree
     * @param  \App\Models\File  $parent
     * @param  \App\Models\User  $user
     * @return void
     */
    public function saveFileTree($tree, $parent, $user)
    {
        foreach ($tree as $name => $file) {
            if (is_array($file)) {
                $folder = new File();
                $folder->is_folder = true;
                $folder->name = $name;

                $parent->appendNode($folder);

                $this->saveFileTree($file, $folder, $user);
            } else {
                $this->saveFile($file, $parent, $user);
            }
        }
    }

    /**
     * Save the individial file.
     *
     * @param  array  $tree
     * @param  \App\Models\File  $parent
     * @param  \App\Models\User  $user
     * @return void
     */
    public function saveFile($file, $parent, $user)
    {
        $path = $file->store('/files/'.$user->id);

        $model = new File();
        $model->is_folder = false;
        $model->storage_path = $path;
        $model->name = $file->getClientOriginalName();
        $model->mime = $file->getMimeType();
        $model->size = $file->getSize();
        $parent->appendNode($model);
    }

    /**
     * Temporary delete the files and/or folders.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(FileActionsRequest $request)
    {
        $payload = $request->validated();
        $parent = $request->parent;

        if ($payload['all']) {
            $children = $parent->children;

            foreach ($children as $child) {
                $child->moveToTrash();
            }
        } else {
            foreach ($payload['ids'] ?? [] as $id) {
                File::find($id)->moveToTrash();
            }
        }

        return to_route('myFiles', ['folder' => $parent->path]);
    }

    /**
     * Restore the file and/or folder.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore(TrashFileRequest $request)
    {
        $payload = $request->validated();
        $ids = $payload['ids'] ?? [];

        if ($payload['all']) {
            $children = File::onlyTrashed()->get();
            foreach ($children as $child) {
                $child->restore();
            }
        } else {
            $children = File::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($children as $child) {
                $child->restore();
            }
        }

        return to_route('trash');
    }

    /**
     * Permanently delete the file and/or folder.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteForever(TrashFileRequest $request)
    {
        $payload = $request->validated();
        $ids = $payload['ids'] ?? [];

        if ($payload['all']) {
            $children = File::onlyTrashed()->get();
            foreach ($children as $child) {
                $child->deleteForever();
            }
        } else {
            $children = File::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($children as $child) {
                $child->deleteForever();
            }
        }

        return to_route('trash');
    }

    /**
     * Add the file(s) or folder(s) to favourites.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleFavourite(AddToFavouritesRequest $request)
    {
        $payload = $request->validated();

        $id = $payload['id'];
        $file = File::find($id);

        $hasFavourited = StarredFile::where('file_id', $file->id)->where('user_id', auth()->id())->first();
        if (! $hasFavourited) {
            $data = [
                'file_id' => $file->id,
                'user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            StarredFile::create($data);
        } else {
            $hasFavourited->delete();
        }

        return back();
    }

    /**
     * Rename a file or folder.
     *
     * @param RenameFileRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rename(RenameFileRequest $request)
    {
        $data = $request->validated();
        
        $file = File::query()
            ->where('id', $data['id'])
            ->where('created_by', auth()->id())
            ->firstOrFail();
        
        // Check if name is different
        if ($file->name === $data['name']) {
            return response()->json([
                'message' => 'File name is the same.'
            ]);
        }
        
        // Update file name
        $file->name = $data['name'];
        $file->save();
        
        return response()->json([
            'message' => 'File renamed successfully.',
            'file' => new FileResource($file)
        ]);
    }

    /**
     * Move a file or folder to another location.
     *
     * @param MoveFileRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function move(MoveFileRequest $request)
    {
        $data = $request->validated();
        
        $file = File::query()
            ->where('id', $data['id'])
            ->where('created_by', auth()->id())
            ->firstOrFail();
        
        // Handle moving to root folder
        if ($data['parent_id'] == 1 || !$data['parent_id']) {
            $destinationFolder = $this->getRoot();
        } else {
            $destinationFolder = File::query()
                ->where('id', $data['parent_id'])
                ->where('created_by', auth()->id())
                ->where('is_folder', true)
                ->firstOrFail();
        }
        
        // Check if trying to move to the same location
        if ($file->parent_id == $destinationFolder->id) {
            return response()->json([
                'message' => 'File is already in this location.'
            ]);
        }
        
        // Check if trying to move a folder into itself or its descendants
        if ($file->is_folder) {
            $ancestors = $destinationFolder->ancestors;
            foreach ($ancestors as $ancestor) {
                if ($ancestor->id == $file->id) {
                    return response()->json([
                        'message' => 'Cannot move folder into itself or its subdirectory.'
                    ], 422);
                }
            }
            if ($destinationFolder->id == $file->id) {
                return response()->json([
                    'message' => 'Cannot move folder into itself.'
                ], 422);
            }
        }
        
        // Move the file/folder
        $destinationFolder->appendNode($file);
        
        return response()->json([
            'message' => 'File moved successfully.',
            'file' => new FileResource($file)
        ]);
    }

    /**
     * Share the selected file(s) and/or folder(s).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function share(ShareFilesRequest $request)
    {
        $payload = $request->validated();
        $parent = $request->parent;

        $all = $payload['all'] ?? false;
        $ids = $payload['ids'] ?? [];
        $email = $payload['email'];

        $user = User::where('email', $email)->first();
        if (! $user) {
            return back();
        }

        if (! $all && empty($ids)) {
            return ['message' => 'Please select at least file or folder to share.'];
        }

        if ($all) {
            $files = $parent->children;
        } else {
            $files = File::find($ids);
        }

        $selectedFileIds = $files->pluck('id')->toArray();
        $sharedFiles = FileShare::whereIn('file_id', $selectedFileIds)
            ->where('user_id', $user->id)
            ->pluck('file_id')
            ->toArray();

        $data = [];
        foreach ($files as $file) {
            if (in_array($file->id, $sharedFiles)) {
                continue;
            }

            $data[] = [
                'file_id' => $file->id,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        FileShare::insert($data);

        Mail::to($user)
            ->send(new ShareFilesMail($user, auth()->user(), $files));

        return back();
    }

    /**
     * Display the files/folders list that are shared with me.
     *
     * @return \Inertia\Inertia
     */
    public function sharedWithMe(?string $folder = null)
    {
        $search = request()->search;

        // If a folder path is provided, try to open that folder (if it's shared with the user)
        if ($folder) {
            $folderModel = File::query()->where('path', $folder)->firstOrFail();

            // Check whether the folder or any of its ancestors is shared with the current user
            $hasAccess = \App\Models\FileShare::where('file_id', $folderModel->id)
                ->where('user_id', auth()->id())
                ->exists();

            if (! $hasAccess) {
                foreach ($folderModel->ancestors as $ancestor) {
                    if (\App\Models\FileShare::where('file_id', $ancestor->id)->where('user_id', auth()->id())->exists()) {
                        $hasAccess = true;
                        break;
                    }
                }
            }

            if (! $hasAccess) {
                abort(403);
            }

            $query = File::query()
                ->select('files.*')
                ->where('parent_id', $folderModel->id)
                ->orderBy('is_folder', 'DESC')
                ->orderBy('files.created_at', 'DESC')
                ->orderBy('files.id', 'DESC')
                ->with(['starred:id,user_id,file_id,created_at']);

            if ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            $files = $query->paginate(10);
            $files = FileResource::collection($files);

            if (request()->wantsJson()) {
                return $files;
            }

            $ancestors = FileResource::collection([...$folderModel->ancestors, $folderModel]);
            $folderResource = new FileResource($folderModel);

            return Inertia::render('SharedWithMe', [
                'files' => $files,
                'search' => $search,
                'folder' => $folderResource,
                'rootFolder' => $folderResource,
                'ancestors' => $ancestors,
            ]);
        }

        $query = File::getSharedWithMe();
        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }
        $files = $query->paginate(10);

        $files = FileResource::collection($files);

        if (request()->wantsJson()) {
            return $files;
        }

        return Inertia::render('SharedWithMe', [
            'files' => $files,
            'search' => $search,
            'folder' => null,
            'ancestors' => FileResource::collection([]),
        ]);
    }

    /**
     * Display the files/folders list that are shared by me.
     *
     * @return \Inertia\Inertia
     */
    public function sharedByMe(?string $folder = null)
    {
        $search = request()->search;

        // If folder path provided, open folder if it belongs to current user
        if ($folder) {
            $folderModel = File::query()
                ->where('path', $folder)
                ->where('created_by', auth()->id())
                ->firstOrFail();

            $query = File::query()
                ->select('files.*')
                ->where('parent_id', $folderModel->id)
                ->orderBy('is_folder', 'DESC')
                ->orderBy('files.created_at', 'DESC')
                ->orderBy('files.id', 'DESC')
                ->with(['starred:id,user_id,file_id,created_at']);

            if ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            $files = $query->paginate(10);
            $files = FileResource::collection($files);

            if (request()->wantsJson()) {
                return $files;
            }

            $ancestors = FileResource::collection([...$folderModel->ancestors, $folderModel]);
            $folderResource = new FileResource($folderModel);

            return Inertia::render('SharedByMe', [
                'files' => $files,
                'search' => $search,
                'folder' => $folderResource,
                'rootFolder' => $folderResource,
                'ancestors' => $ancestors,
            ]);
        }

        $query = File::getSharedByMe();
        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }
        $files = $query->paginate(10);

        $files = FileResource::collection($files);

        if (request()->wantsJson()) {
            return $files;
        }

        return Inertia::render('SharedByMe', [
            'files' => $files,
            'search' => $search,
            'folder' => null,
            'ancestors' => FileResource::collection([]),
        ]);
    }
}
