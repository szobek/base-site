<?php

namespace App\Http\Requests\Media;

use App\Models\User;
use App\Services\MediaLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->rulesForRequest();

        return [
            'file' => [
                'required',
                'file',
                'max:'.$rules['max_kilobytes'],
                'mimetypes:'.implode(',', $rules['mimes']),
            ],
            'collection' => ['nullable', 'string', Rule::in(array_keys(config('media.collections')))],
            'alt' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $rules = $this->rulesForRequest();

        return [
            'file.required' => 'Válassz egy fájlt.',
            'file.max' => 'A fájl legfeljebb '.$this->limits()->sizeLabel($rules['max_kilobytes']).' lehet.',
            'file.mimetypes' => 'Engedett típusok: '.implode(', ', $rules['mime_labels']).'.',
            'collection.in' => 'Ismeretlen média gyűjtemény.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->user() instanceof User || ! $this->file('file')) {
                return;
            }

            $collection = $this->input('collection') ?: 'default';

            if ($this->limits()->find($collection) === null) {
                return;
            }

            try {
                $this->limits()->ensureCanStore(
                    $this->user(),
                    $collection,
                    (int) ($this->file('file')->getSize() ?: 0),
                );
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }

    /**
     * @return array{mimes: list<string>, mime_labels: list<string>, max_kilobytes: int, max_files: int|null}
     */
    private function rulesForRequest(): array
    {
        $name = $this->input('collection') ?: 'default';

        return $this->limits()->find(is_string($name) ? $name : 'default')
            ?? $this->limits()->collection('default');
    }

    private function limits(): MediaLimits
    {
        return app(MediaLimits::class);
    }
}
