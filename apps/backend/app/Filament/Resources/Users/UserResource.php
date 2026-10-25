<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use UnitEnum;

/** Staff accounts and their roles. What each role may do is set under রোল (Shield). */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'সেটিংস';

    protected static ?string $navigationLabel = 'স্টাফ';

    protected static ?string $modelLabel = 'স্টাফ';

    protected static ?string $pluralModelLabel = 'স্টাফ';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('নাম')->required()->maxLength(100),
            TextInput::make('email')->label('ইমেইল (লগইন)')->email()->required()->maxLength(150)->unique(ignoreRecord: true),
            TextInput::make('password')->label('পাসওয়ার্ড')->password()->revealable()
                ->rule(Password::min(10))
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'বদলাতে না চাইলে খালি রাখুন' : 'অন্তত ১০ অক্ষর'),
            Select::make('roles')->label('রোল')->relationship('roles', 'name')->multiple()->preload()->required()
                ->getOptionLabelFromRecordUsing(fn ($role) => RoleSeeder::LABELS[$role->name] ?? $role->name)
                // Only a super admin can hand out super admin.
                ->disableOptionWhen(fn (string $label, $value) => ! auth()->user()?->hasRole('super_admin')
                    && Role::find($value)?->name === 'super_admin'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('নাম')->searchable(),
                TextColumn::make('email')->label('ইমেইল')->searchable(),
                TextColumn::make('roles.name')->label('রোল')->badge()
                    ->formatStateUsing(fn (string $state) => RoleSeeder::LABELS[$state] ?? $state),
                IconColumn::make('two_factor')->label('2FA')->boolean()
                    ->state(fn (User $record) => filled($record->app_authentication_secret)),
                TextColumn::make('created_at')->label('যোগ হয়েছে')->date()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                // For a lost phone without recovery codes: they set it up again at next login.
                Action::make('resetTwoFactor')->label('2FA রিসেট')->icon(Heroicon::OutlinedKey)->color('warning')
                    ->visible(fn (User $record) => filled($record->app_authentication_secret) && auth()->user()?->hasRole('super_admin') && ! $record->is(auth()->user()))
                    ->requiresConfirmation()
                    ->modalDescription('এই স্টাফের অথেনটিকেটর অ্যাপ আর রিকভারি কোড মুছে যাবে; পরের লগইনে নতুন করে সেটআপ করতে হবে।')
                    ->action(function (User $record) {
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);
                        Notification::make()->success()->title('2FA রিসেট হয়েছে')->send();
                    }),
                DeleteAction::make()->hidden(fn (User $record) => $record->is(auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
