<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileActionsRequest;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadController extends Controller
{
    /**
     * Donwload the file(s) or folder(s) from My Files section.
     *
     * @return array
     */
    public function fromMyFiles(FileActionsRequest $request)
    {
        $payload = $request->validated();
        $parent = $request->parent;

        $all = $payload['all'] ?? false;
        $ids = $payload['ids'] ?? [];

        if (! $all && empty($ids)) {
            return ['message' => 'Please select at least one file or one folder to download.'];
        }

        if ($all) {
            $url = $this->createZip($parent->children);
            $filename = $parent->name.'.zip';
        } else {
            [$url, $filename] = $this->getDownloadUrl($ids, $parent->name);
        }

        return [
            'url' => $url,
            'filename' => $filename,
        ];
    }

    /**
     * Donwload the file(s) or folder(s) from shared with me page.
     *
     * @return array
     */
    public function sharedWithMe(FileActionsRequest $request)
    {
        $payload = $request->validated();

        $all = $payload['all'] ?? false;
        $ids = $payload['ids'] ?? [];

        if (! $all && empty($ids)) {
            return ['message' => 'Please select at least one file or one folder to download.'];
        }

        $zipFileName = 'shared-with-me';
        if ($all) {
            $files = File::getSharedWithMe()->get();
            $url = $this->createZip($files);
            $filename = $zipFileName.'.zip';
        } else {
            [$url, $filename] = $this->getDownloadUrl($ids, $zipFileName);
        }

        return [
            'url' => $url,
            'filename' => $filename,
        ];
    }

    /**
     * Donwload the file(s) or folder(s) from shared by me page.
     *
     * @return array
     */
    public function sharedByMe(FileActionsRequest $request)
    {
        $payload = $request->validated();

        $all = $payload['all'] ?? false;
        $ids = $payload['ids'] ?? [];

        if (! $all && empty($ids)) {
            return ['message' => 'Please select at least one file or one folder to download.'];
        }

        $zipFileName = 'shared-by-me';
        if ($all) {
            $files = File::getSharedByMe()->get();
            $url = $this->createZip($files);
            $filename = $zipFileName.'.zip';
        } else {
            [$url, $filename] = $this->getDownloadUrl($ids, $zipFileName);
        }

        return [
            'url' => $url,
            'filename' => $filename,
        ];
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
     * Get the download url and file name.
     *
     * @param  array  $ids
     * @param  string  $zipName
     * @return array
     */
    private function getDownloadUrl($ids, $zipName)
    {
        if (count($ids) === 1) {
            $file = File::find($ids[0]);
            if (! $file) {
                return ['message' => 'File not found.'];
            }
            if ($file->is_folder) {
                if ($file->children->count() === 0) {
                    return ['message' => 'The folder is empty.'];
                }
                $url = $this->createZip($file->children);
                $filename = $file->name.'.zip';
            } else {
                // Check if file exists
                if (!Storage::exists($file->storage_path)) {
                    return ['message' => 'File not found in storage.'];
                }
                $basename = pathinfo($file->storage_path, PATHINFO_BASENAME);
                $destination = 'public/'.$basename;
                // Only copy if not already exists or is outdated
                if (!Storage::exists($destination) || Storage::lastModified($destination) < Storage::lastModified($file->storage_path)) {
                    Storage::copy($file->storage_path, $destination);
                }
                $url = asset(Storage::url($basename));
                $filename = $file->name;
               
            }
        } else {
            $files = File::whereIn('id', $ids)->get();
            $url = $this->createZip($files);
            $filename = $zipName.'.zip';
        }

        return [$url, $filename];
    }

    /**
     * Create the zip containing files and/or folders that the user
     * has requested to download.
     *
     * @param  array  $files
     * @return string
     */
    private function createZip($files)
    {
        $zipPath = 'zip/'.Str::random().'.zip';
        $publicPath = "public/$zipPath";

        if (! is_dir(dirname($publicPath))) {
            Storage::makeDirectory(dirname($publicPath));
        }

        $zipFile = Storage::path($publicPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $this->addFilesToZip($zip, $files);
        }

        $zip->close();

        return asset(Storage::url($zipPath));
    }
}
