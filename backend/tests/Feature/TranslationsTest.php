<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TranslationsTest extends TestCase
{
    public function test_validation_messages_are_in_spanish(): void
    {
        $errors = Validator::make(
            ['password' => 'corta', 'email' => 'no-es-correo'],
            ['password' => 'min:12', 'email' => 'email', 'name' => 'required'],
        )->errors();

        $this->assertSame('Contraseña debe tener al menos 12 caracteres.', $errors->first('password'));
        $this->assertSame('Correo debe ser un correo electrónico válido.', $errors->first('email'));
        $this->assertSame('Nombre es obligatorio.', $errors->first('name'));
    }

    public function test_every_english_validation_key_has_a_spanish_translation(): void
    {
        $en = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $es = require lang_path('es/validation.php');

        $missing = array_diff(array_keys($en), array_keys($es));
        $this->assertSame([], array_values($missing), 'Faltan traducciones: '.implode(', ', $missing));
    }
}
