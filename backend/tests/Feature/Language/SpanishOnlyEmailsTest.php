<?php

namespace Tests\Feature\Language;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Los correos que envía el sistema (plantilla de Laravel) también van en español. */
class SpanishOnlyEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_email_is_in_spanish(): void
    {
        ResetPassword::createUrlUsing(fn () => 'https://tienda.test/restablecer');
        $user = User::factory()->create();
        $html = (string) (new ResetPassword('token'))->toMail($user)->render();

        $this->assertStringContainsString('¡Hola!', $html);
        $this->assertSame([], SpanishOnlyAdminTest::englishFragments($html));
    }

    public function test_http_error_pages_are_in_spanish(): void
    {
        $this->get('/no-existe-esta-pagina')->assertNotFound()->assertSee('No encontrado');
    }
}
