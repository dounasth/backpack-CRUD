<?php

namespace Backpack\CRUD\app\Library\CrudPanel\Uploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class MultipleFiles extends Uploader
{
    public static function for(array $field, $configuration)
    {
        return (new self($field, $configuration))->multiple();
    }

    public function uploadFile(Model $entry, $value = null)
    {
        $filesToDelete = CRUD::getRequest()->get('clear_'.$this->name);

        $value = $value ?? CRUD::getRequest()->file($this->name);

        $previousFiles = $entry->getOriginal($this->name) ?? [];

        if (! is_array($previousFiles) && is_string($previousFiles)) {
            $previousFiles = json_decode($previousFiles, true);
        }

        if ($filesToDelete) {
            foreach ($previousFiles as $previousFile) {
                if (in_array($previousFile, $filesToDelete)) {
                    Storage::disk($this->disk)->delete($previousFile);

                    $previousFiles = Arr::where($previousFiles, function ($value, $key) use ($previousFile) {
                        return $value != $previousFile;
                    });
                }
            }
        }

        foreach ($value ?? [] as $file) {
            if ($file && is_file($file)) {
                $fileName = $this->getFileNameWithExtension($file);

                $file->storeAs($this->path, $fileName, $this->disk);

                $previousFiles[] = $this->path.$fileName;
            }
        }

        return isset($entry->getCasts()[$this->name]) ? $previousFiles : json_encode($previousFiles);
    }

    public function uploadRepeatableFile(Model $entry, $files = null)
    {
        $previousFiles = $this->getPreviousRepeatableValues($entry);

        $fileOrder = $this->getFileOrderFromRequest();

        foreach ($files as $row => $files) {
            foreach ($files ?? [] as $file) {
                if ($file && is_file($file)) {
                    $fileName = $this->getFileNameWithExtension($file);

                    $file->storeAs($this->path, $fileName, $this->disk);
                    $fileOrder[$row][] = $this->path.$fileName;
                }
            }
        }

        foreach ($previousFiles as $previousRow => $files) {
            foreach ($files ?? [] as $key => $file) {
                $key = array_search($file, $fileOrder, true);
                if ($key === false) {
                    Storage::disk($this->disk)->delete($file);
                }
            }
        }

        return $fileOrder;
    }
}