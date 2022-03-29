<?php

namespace Froala\NovaFroalaField\Handlers;

use Froala\NovaFroalaField\Froala;
use Froala\NovaFroalaField\Models\PendingAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Manipulations;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\Image\Image;

class StorePendingAttachment
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
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    public function __invoke(Request $request)
    {
        $this->abortIfFileNameExists($request);

        $attachmentModel = PendingAttachment::create([
            'draft_id' => $request->draftId,
            'attachment' => config('nova.froala-field.preserve_file_names')
                ? $request->attachment->storeAs(
                    $this->field->getStorageDir(),
                    $request->attachment->getClientOriginalName(),
                    $this->field->disk
                ) : $request->attachment->store($this->field->getStorageDir(), $this->field->disk),
            'origin_filename' => $request->attachment->getClientOriginalName(),
            'disk' => $this->field->disk,
        ]);
        
        $attachment = $attachmentModel->attachment;

        $this->imageOptimize($attachment);

        $newAttachment = $this->convertImageToWebp($attachmentModel);

        return Storage::disk($this->field->disk)->url($newAttachment);
    }

    protected function abortIfFileNameExists(Request $request): void
    {
        $path = rtrim($this->field->getStorageDir(), '/').'/'.$request->attachment->getClientOriginalName();

        if (config('nova.froala-field.preserve_file_names')
            && Storage::disk($this->field->disk)
                ->exists($path)
        ) {
            abort(response()->json([
                'status' => Response::HTTP_CONFLICT,
            ], Response::HTTP_CONFLICT));
        }
    }

    protected function imageOptimize(string $attachment): void
    {
        if (config('nova.froala-field.optimize_images')) {
            $optimizerChain = OptimizerChainFactory::create();

            if (count($optimizers = config('nova.froala-field.image_optimizers'))) {
                $optimizers = array_map(
                    function (array $optimizerOptions, string $optimizerClassName) {
                        return (new $optimizerClassName)->setOptions($optimizerOptions);
                    },
                    $optimizers,
                    array_keys($optimizers)
                );

                $optimizerChain->setOptimizers($optimizers);
            }

            $optimizerChain->optimize(Storage::disk($this->field->disk)->path($attachment));
            
        }
    }

    protected function convertImageToWebp(PendingAttachment $attachmentModel): string
    {
        $oldAttachment  = $attachmentModel->attachment;
        $attachmentName = explode('.', $oldAttachment);
        $newAttachment  = $attachmentName[0] . '.webp';
        $thumbFile      = $attachmentName[0] . "_thumb.webp";
        
        // Convert image to webp image
        Image::load(Storage::disk($this->field->disk)->path($oldAttachment))
            ->format(Manipulations::FORMAT_WEBP)
            ->save(Storage::disk($this->field->disk)->path($newAttachment));
        // Store thumb image
        Image::load(Storage::disk($this->field->disk)->path($oldAttachment))
            ->format(Manipulations::FORMAT_WEBP)
            ->width(250)
            ->height(250)
            ->save(Storage::disk($this->field->disk)->path($thumbFile));
        
        $attachmentModel->attachment = $newAttachment;
        $attachmentModel->save();

        Storage::disk($this->field->disk)->delete($oldAttachment);

        return $newAttachment;
    }
}
