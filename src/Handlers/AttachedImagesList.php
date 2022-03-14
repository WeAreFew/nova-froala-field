<?php

namespace Froala\NovaFroalaField\Handlers;

use Froala\NovaFroalaField\Froala;
use Froala\NovaFroalaField\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachedImagesList
{
    /**
     * The field instance.
     *
     * @var \Froala\NovaFroalaField\Froala
     */
    public $field;

    /**
     * Create a new invokable instance.
     *
     * @param  \Froala\NovaFroalaField\Froala  $field
     * @return void
     */
    public function __construct(Froala $field)
    {
        $this->field = $field;
    }

    /**
     * Attach a pending attachment to the field.
     */
    public function __invoke(Request $request): array
    {
        $images = [];

        $disk = Storage::disk($this->field->disk);

        foreach (Attachment::all() as $file) {
            $fileType = pathinfo($file->attachment, PATHINFO_EXTENSION);

            $thumbUrl = substr_replace($file->url, "_thumb", strpos($file->url, '.' . $fileType)) . '.' . $fileType;
            $thumbFilename = substr_replace($file->attachment, "_thumb", strpos($file->attachment, '.' . $fileType)) . '.' . $fileType;

            $images[] = [
                'url' => $file->url,
                'thumb' => $disk->exists($thumbFilename) ? $thumbUrl : $file->url,
            ];
        }

        return $images;
    }
}
