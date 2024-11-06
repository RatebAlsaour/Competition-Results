<?php

namespace App\Http\Services;

use App\Exceptions\FileStorageException;
use App\Http\Classes\File;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    /**
     * Store files from request.
     *
     * @param  UploadedFile  $media
     * @param  string        $folderName
     * @param  string        $mediaName
     * @return ?File
     * @throws FileStorageException
     */
    public static function storeFiles(?UploadedFile $media, string $folderName, string $mediaName = 'default'): ?File
    {
        if(!isset($media))
        {
            return null;
        }

        try
        {
            $folderName = kebabCase($folderName);
            $folderName = $folderName . '/' . date('Y-m-d');

            $mediaName = pathinfo($media->getClientOriginalName(), PATHINFO_FILENAME);
            $mediaName = kebabCase($mediaName);
            $mediaName = $mediaName . '-' . Carbon::now()->microsecond;
            $mediaName = Str::slug($mediaName, '-') . '.' . $media->extension();

            $path = $media->storeAs($folderName, $mediaName, 'public');

            // Get media type like ( video - image - document ...etc )
            $mime = $media->getClientMimeType();
            $mediaType = explode('/', $mime)[0];

            if ($path && $mediaType)
            {
                return new File(
                    path: Storage::url($path),
                    type: $mediaType
                );
            }

            throw new FileStorageException(trans('file.store'), 400);
        }
        catch (\Exception $e)
        {
            throw new FileStorageException(trans('file.store'), 400);
        }
    }

    /**
     * store the base64 file and return the path in file storage
     * @var string $base64_file
     * @var string $folderName
     * @var string $mediaName
     * @return File
     */
    public static function storeBase64File(string $base64_file, string $folderName, string $mediaName='default'): File
    {
        try
        {
            $folderName = str_replace(' ', '-', $folderName);
            $folderName = $folderName . '/' . date('Y-m-d');

            if(!str_contains($base64_file,';'))
                throw new FileStorageException('invalid file format', 400);

            $explodedBase64 = explode(';', $base64_file);
            $type = $explodedBase64[0];
            $file_string = $explodedBase64[count($explodedBase64)-1];

            //explode the type string to get extension from it
            $typeElements = explode('/', $type);

            //extension will be the last item in the array
            $fileExtension = $typeElements[count($typeElements)-1];

            $mediaName = str_replace(' ', '-', $mediaName);
            $mediaName = $mediaName.'-'.Carbon::now()->microsecond . '.' . $fileExtension;

            list(, $fileEncoded) = explode(',', $file_string);
            Storage::disk('public')->put($folderName.'/'.$mediaName, base64_decode($fileEncoded));
            $path = 'storage'.'/'.$folderName.'/'.$mediaName;

            if ($path)
            {
                return new File(
                    path: $path,
                    type: 'image'
                );
            }

            throw new FileStorageException(trans('file.store'), 400);
        }
        catch (\Exception $e)
        {
            throw new FileStorageException(trans('file.store'), 400);
        }
    }

}
