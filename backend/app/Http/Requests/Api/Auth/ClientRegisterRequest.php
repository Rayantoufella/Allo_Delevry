<?php

namespace App\Http\Requests\Api\Auth;

use App\Models\DriverProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inscription d'un client dans le contexte d'un livreur précis
 * (POST /drivers/{slug}/register). Le rôle "client" et le rattachement au
 * livreur sont forcés côté contrôleur à partir du slug de l'URL, jamais du
 * corps de la requête.
 */
class ClientRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $driverId = DriverProfile::where('slug', $this->route('slug'))->value('user_id');

        abort_unless($driverId !== null, 404);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->where('driver_id', $driverId)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est requis.',
            'email.required' => "L'adresse e-mail est requise.",
            'email.email' => "L'adresse e-mail doit être valide.",
            'email.unique' => 'Cette adresse e-mail est déjà utilisée chez ce livreur.',
            'password.required' => 'Le mot de passe est requis.',
            'password.min' => 'Le mot de passe doit contenir au moins :min caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'phone.string' => 'Le numéro de téléphone doit être une chaîne de caractères.',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser :max caractères.',
        ];
    }
}
