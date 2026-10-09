<?php

namespace App\Console\Commands;

use App\Actions\User\BootstrapAdmin;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use LogicException;

class BootstrapAdminCommand extends Command
{
    protected $signature = 'siga:bootstrap-admin
                            {--name= : Nombre del administrador inicial}
                            {--email= : Correo de acceso del administrador inicial}';

    protected $description = 'Crea el primer administrador del sistema SIGA';

    public function handle(
        BootstrapAdmin $bootstrapAdmin
    ): int {
        $name = $this->option('name');

        if ($name === null) {
            $name = $this->ask(
                'Nombre del administrador'
            );
        }

        $email = $this->option('email');

        if ($email === null) {
            $email = $this->ask(
                'Correo del administrador'
            );
        }

        $password = $this->secret(
            'Contraseña del administrador'
        );

        $confirmation = $this->secret(
            'Confirme la contraseña'
        );

        if ($password !== $confirmation) {
            $this->error(
                'Las contraseñas no coinciden.'
            );

            return self::FAILURE;
        }

        try {
            $bootstrapAdmin->execute(
                (string) $name,
                (string) $email,
                (string) $password
            );
        } catch (ValidationException $error) {
            $messages = $error->errors();

            $firstFieldErrors = reset(
                $messages
            );

            $message = is_array($firstFieldErrors)
                ? ($firstFieldErrors[0] ?? null)
                : null;

            $this->error(
                is_string($message)
                    ? $message
                    : 'Los datos del administrador no son válidos.'
            );

            return self::FAILURE;
        } catch (LogicException $error) {
            $this->error(
                $error->getMessage()
            );

            return self::FAILURE;
        }

        $this->info(
            'Administrador inicial creado correctamente.'
        );

        return self::SUCCESS;
    }
}
