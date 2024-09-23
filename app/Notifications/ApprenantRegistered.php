<?php

// app/Notifications/ApprenantRegistered.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprenantRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    protected $user;
    protected $password;

    public function __construct($user, $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Inscription réussie')
                    ->greeting('Bonjour ' . $this->user->prenom . ' ' . $this->user->nom . '!')
                    ->line('Votre inscription a été réussie. Voici vos informations de connexion :')
                    ->line('Email : ' . $this->user->email)
                    ->line('Mot de passe : ' . $this->password)
                    ->action('Se connecter', url('/login'))
                    ->line('Merci de vous inscrire avec nous!');
    }

    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
