<?php

namespace App\Http\Controllers;

use App\Enums\CustomFieldEntity;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Auth-gated download of a custom-field file. Files are stored on the private
 * `local` disk (never publicly accessible); the path is looked up from the
 * model's details bag by entity + record + key, so no raw path comes from the
 * client.
 */
class CustomFieldFileController extends Controller
{
    public function __invoke(string $entity, int|string $record, string $key): StreamedResponse
    {
        $resolved = CustomFieldEntity::tryFrom($entity);

        abort_if($resolved === null, 404);

        $model = $resolved->modelClass()::query()->findOrFail($record);
        $path = data_get($model->details, $key);

        abort_unless(
            is_string($path) && $path !== '' && Storage::disk('local')->exists($path),
            404,
        );

        return Storage::disk('local')->download($path);
    }
}
