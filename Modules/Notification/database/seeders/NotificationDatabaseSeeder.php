<?php

namespace Modules\Notification\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationTemplate;

class NotificationDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            NotificationTemplateSeeder::class,
        ]);
    }
}

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Welcome Email',
                'slug' => 'welcome-email',
                'subject' => 'Welcome to {{ app_name }}!',
                'body' => "Hello {{ user_name }},\n\nWelcome to {{ app_name }}! We're excited to have you on board.\n\nGet started by exploring our features and setting up your profile.\n\nBest regards,\nThe {{ app_name }} Team",
                'category' => NotificationCategory::COMMUNICATION,
                'channels' => [NotificationChannel::DATABASE->value, NotificationChannel::EMAIL->value],
                'variables' => ['user_name', 'app_name'],
                'is_active' => true,
            ],
            [
                'name' => 'Password Reset',
                'slug' => 'password-reset',
                'subject' => 'Reset Your Password',
                'body' => "Hello {{ user_name }},\n\nYou requested a password reset for your account. Click the button below to reset your password.\n\nIf you didn't request this, please ignore this email.\n\nThis link will expire in 60 minutes.",
                'category' => NotificationCategory::SECURITY,
                'channels' => [NotificationChannel::DATABASE->value, NotificationChannel::EMAIL->value],
                'variables' => ['user_name', 'reset_link'],
                'is_active' => true,
            ],
            [
                'name' => 'Login Alert',
                'slug' => 'login-alert',
                'subject' => 'New Login to Your Account',
                'body' => "Hello {{ user_name }},\n\nA new login was detected on your account.\n\nDevice: {{ device }}\nLocation: {{ location }}\nTime: {{ time }}\n\nIf this wasn't you, please secure your account immediately.",
                'category' => NotificationCategory::SECURITY,
                'channels' => [NotificationChannel::DATABASE->value, NotificationChannel::EMAIL->value],
                'variables' => ['user_name', 'device', 'location', 'time'],
                'is_active' => true,
            ],
            [
                'name' => 'Order Confirmation',
                'slug' => 'order-confirmation',
                'subject' => 'Order #{{ order_number }} Confirmed',
                'body' => "Hello {{ user_name }},\n\nYour order #{{ order_number }} has been confirmed!\n\nTotal: {{ order_total }}\n\nWe'll notify you when your order ships.\n\nThank you for shopping with us!",
                'category' => NotificationCategory::TRANSACTIONAL,
                'channels' => [NotificationChannel::DATABASE->value, NotificationChannel::EMAIL->value],
                'variables' => ['user_name', 'order_number', 'order_total'],
                'is_active' => true,
            ],
            [
                'name' => 'System Maintenance',
                'slug' => 'system-maintenance',
                'subject' => 'Scheduled Maintenance Notice',
                'body' => "Hello {{ user_name }},\n\nWe'll be performing scheduled maintenance on {{ maintenance_date }} from {{ start_time }} to {{ end_time }}.\n\nDuring this time, the service may be temporarily unavailable.\n\nWe apologize for any inconvenience.",
                'category' => NotificationCategory::SYSTEM,
                'channels' => [NotificationChannel::DATABASE->value, NotificationChannel::EMAIL->value],
                'variables' => ['user_name', 'maintenance_date', 'start_time', 'end_time'],
                'is_active' => true,
            ],
            [
                'name' => 'New Feature Announcement',
                'slug' => 'new-feature',
                'subject' => 'Exciting New Feature: {{ feature_name }}',
                'body' => "Hello {{ user_name }},\n\nWe're thrilled to announce a new feature: {{ feature_name }}!\n\n{{ feature_description }}\n\nTry it out today and let us know what you think!",
                'category' => NotificationCategory::MARKETING,
                'channels' => [NotificationChannel::DATABASE->value, NotificationChannel::EMAIL->value],
                'variables' => ['user_name', 'feature_name', 'feature_description'],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
