<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileActionsRequest;
use App\Models\File;
use App\Models\FileShare;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadController extends Controller
{
    /**
     * Preview image file inline without exposing storage path.
     *
     * @return \Illuminate\Http\Response
     */
    public function preview(File $file)
    {
        if ($file->is_folder) {
            return response()->json(['message' => 'Folder cannot be previewed.'], 400);
        }

        $mime = $file->mime ?: Storage::mimeType($file->storage_path);

        $canPreview = $mime
            && (str_starts_with($mime, 'image/') || in_array($mime, ['application/pdf', 'application/x-pdf'], true));

        if (! $canPreview) {
            return response()->json(['message' => 'Preview is only available for image and PDF files.'], 400);
        }

        if (! $this->canPreviewFile($file)) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        if (! Storage::exists($file->storage_path)) {
            return response()->json(['message' => 'File not found in storage.'], 404);
        }

        return response()->file(Storage::path($file->storage_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$file->name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Donwload the file(s) or folder(s) from My Files section.
     *
     * @return \Illuminate\Http\Response|array
     */
    public function fromMyFiles(FileActionsRequest $request)
    {
        $payload = $request->validated();
        $parent = $request->parent;

        $all = $payload['all'] ?? false;
        $ids = $payload['ids'] ?? [];

        if (! $all && empty($ids)) {
            return response()->json(['message' => 'Please select at least one file or one folder to download.'], 400);
        }

        if ($all) {
            // Check authorization for all files
            if (!$this->canAccessFiles($parent->children)) {
                return response()->json(['message' => 'Unauthorized access.'], 403);
            }
            return $this->downloadZip($parent->children, $parent->name.'.zip');
        } else {
            return $this->downloadFiles($ids, $parent->name);
        }
    }

    /**
     * Donwload the file(s) or folder(s) from shared with me page.
     *
     * @return \Illuminate\Http\Response|array
     */
    public function sharedWithMe(FileActionsRequest $request)
    {
        $payload = $request->validated();

        $all = $payload['all'] ?? false;
        $ids = $payload['ids'] ?? [];

        if (! $all && empty($ids)) {
            return response()->json(['message' => 'Please select at least one file or one folder to download.'], 400);
        }

        $zipFileName = 'shared-with-me';
        if ($all) {
            $files = File::getSharedWithMe()->get();
            return $this->downloadZip($files, $zipFileName.'.zip');
        } else {
            return $this->downloadFiles($ids, $zipFileName, true);
        }
    }

    /**
     * Donwload the file(s) or folder(s) from shared by me page.
     *
     * @return \Illuminate\Http\Response|array
     */
    public function sharedByMe(FileActionsRequest $request)
    {
        $payload = $request->validated();

        $all = $payload['all'] ?? false;
        $ids = $payload['ids'] ?? [];

        if (! $all && empty($ids)) {
            return response()->json(['message' => 'Please select at least one file or one folder to download.'], 400);
        }

        $zipFileName = 'shared-by-me';
        if ($all) {
            $files = File::getSharedByMe()->get();
            return $this->downloadZip($files, $zipFileName.'.zip');
        } else {
            return $this->downloadFiles($ids, $zipFileName, false, true);
        }
    }

    /**
     * Add the given files into the provided zip archive.
     *
     * @param  \ZipArchive  $zip
     * @param  array  $files
     * @param  string  $ancestors
     * @return void
     */
    private function addFilesToZip($zip, $files, $ancestors = '')
    {
        foreach ($files as $file) {
            if ($file->is_folder) {
                $this->addFilesToZip($zip, $file->children, $ancestors.$file->name.'/');
            } else {
                $zip->addFile(Storage::path($file->storage_path), $ancestors.$file->name);
            }
        }
    }

    /**
     * Check if user can access the files.
     *
     * @param  \Illuminate\Support\Collection  $files
     * @param  bool  $checkShared
     * @param  bool  $checkSharedByMe
     * @return bool
     */
    private function canAccessFiles($files, $checkShared = false, $checkSharedByMe = false)
    {
        foreach ($files as $file) {
            // Check if user owns the file
            if ($file->created_by === auth()->id()) {
                continue;
            }

            // Check if file is shared with user
            if ($checkShared) {
                $isShared = \App\Models\FileShare::where('file_id', $file->id)
                    ->where('user_id', auth()->id())
                    ->exists();
                if ($isShared) {
                    continue;
                }
            }

            // Check if file is shared by user
            if ($checkSharedByMe) {
                if ($file->created_by === auth()->id()) {
                    continue;
                }
            }

            // If none of the conditions met, unauthorized
            return false;
        }

        return true;
    }

    /**
     * Check if the current user can preview the file.
     * Allows: owner, direct share, or share inherited from an ancestor folder.
     *
     * @return bool
     */
    private function canPreviewFile(File $file)
    {
        if ($file->created_by === auth()->id()) {
            return true;
        }

        $isDirectlyShared = FileShare::where('file_id', $file->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($isDirectlyShared) {
            return true;
        }

        foreach ($file->ancestors as $ancestor) {
            $isAncestorShared = FileShare::where('file_id', $ancestor->id)
                ->where('user_id', auth()->id())
                ->exists();

            if ($isAncestorShared) {
                return true;
            }
        }

        return false;
    }

    /**
     * Download files with authorization check.
     *
     * @param  array  $ids
     * @param  string  $zipName
     * @param  bool  $checkShared
     * @param  bool  $checkSharedByMe
     * @return \Illuminate\Http\Response
     */
    private function downloadFiles($ids, $zipName, $checkShared = false, $checkSharedByMe = false)
    {
        if (count($ids) === 1) {
            $file = File::find($ids[0]);
            if (! $file) {
                return response()->json(['message' => 'File not found.'], 404);
            }

            // Authorization check
            if (!$this->canAccessFiles(collect([$file]), $checkShared, $checkSharedByMe)) {
                return response()->json(['message' => 'Unauthorized access.'], 403);
            }

            if ($file->is_folder) {
                if ($file->children->count() === 0) {
                    return response()->json(['message' => 'The folder is empty.'], 400);
                }
                return $this->downloadZip($file->children, $file->name.'.zip');
            } else {
                // Check if file exists in storage
                if (!Storage::exists($file->storage_path)) {
                    return response()->json(['message' => 'File not found in storage.'], 404);
                }
                // Direct download from private storage
                return response()->download(Storage::path($file->storage_path), $file->name);
            }
        } else {
            $files = File::whereIn('id', $ids)->get();
            
            // Authorization check
            if (!$this->canAccessFiles($files, $checkShared, $checkSharedByMe)) {
                return response()->json(['message' => 'Unauthorized access.'], 403);
            }

            return $this->downloadZip($files, $zipName.'.zip');
        }
    }

    /**
     * Create zip and return download response.
     *
     * @param  \Illuminate\Support\Collection  $files
     * @param  string  $zipName
     * @return \Illuminate\Http\Response
     */
    private function downloadZip($files, $zipName)
    {
        $zipPath = 'temp-zip/'.Str::random().'.zip';
        $fullPath = Storage::path($zipPath);

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($fullPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $this->addFilesToZip($zip, $files);
            $zip->close();
        } else {
            return response()->json(['message' => 'Failed to create zip file.'], 500);
        }

        // Return download response and delete zip after sending
        return response()->download($fullPath, $zipName)->deleteFileAfterSend(true);
    }
}
