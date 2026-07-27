<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rating = ['nullable', 'numeric', 'min:0.5', 'max:5', 'multiple_of:0.5'];

        return [
            'tmdb_id' => ['required', 'integer'],
            'watched_on' => ['nullable', 'date'],
            'is_rewatch' => ['boolean'],
            'rating_story' => $rating,
            'rating_direction' => $rating,
            'rating_acting' => $rating,
            'rating_cinematography' => $rating,
            'rating_music' => $rating,
            'review' => ['nullable', 'string', 'max:10000'],
            'quote' => ['nullable', 'string', 'max:500'],
            'contains_spoilers' => ['boolean'],
        ];
    }
}
