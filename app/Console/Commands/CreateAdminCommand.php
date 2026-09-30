<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {--name=} {--email=} {--password=}';

    protected $description = 'إنشاء مستخدم للوحة التحكم (أو تغيير كلمة مرور مستخدم موجود)';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('الاسم');
        $email = $this->option('email') ?? $this->ask('البريد الإلكتروني');
        $password = $this->option('password') ?? $this->secret('كلمة المرور (8 أحرف على الأقل)');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails())
        {
            foreach ($validator->errors()->all() as $error) $this->error($error);
            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);

        $this->info(($user->wasRecentlyCreated ? 'تم إنشاء المستخدم: ' : 'تم تحديث المستخدم: ') . $user->email);

        return self::SUCCESS;
    }
}
