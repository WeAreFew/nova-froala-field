<?php

namespace Froala\NovaFroalaField\Handlers;

use Froala\NovaFroalaField\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DetachAttachment
{
    /**
     * Delete an attachment from the field.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __invoke(Request $request)
    {
        $pathAry = explode("/", $request->src);

        $attaches = Attachment::where('url', $request['data-url'])->get();
        foreach ($attaches as $attach) {
            Storage::disk($attach->disk)->delete(end($pathAry));
        }

        $attaches->each->purge();
    }
}
