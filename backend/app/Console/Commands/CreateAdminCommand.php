<?php

namespace App\Console\Commands;

use App\Domain\System\Services\AuditLogger;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'flux:admin
        {--name= : Nombre}
        {--email= : Correo}
        {--password= : Contraseña (evitar en producción: queda en el historial de la terminal)}';

    protected $description = 'Crea un usuario Superadministrador técnico para el panel /admin';

    public function handle(AuditLogger $audit): int
    {
        $data = [
            'name' => $this->option('name') ?? text('Nombre', required: true),
            'email' => $this->option('email') ?? text('Correo', required: true),
            'password' => $this->option('password') ?? password('Contraseña (mínimo 12 caracteres)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('super-admin');

        $audit->record('user.created_via_cli', $user, [], ['email' => $user->email, 'role' => 'super-admin']);

        $this->info("Administrador creado: {$user->email}. Ingresa en ".rtrim(config('app.url'), '/').'/admin');

        return self::SUCCESS;
    }
}
