<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\AddressRelationManager;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Midtrans\Config;
use Midtrans\Transaction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make()->schema([
                    Section::make('Order Information')->schema([
                        Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'credit_card' => 'Credit Card',
                                'cod' => 'Cash on Delivery',
                            ])
                            ->required(),

                        Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'failed' => 'Failed',
                            ])
                            ->default('pending')
                            ->required(),

                        ToggleButtons::make('status')
                            ->inline()
                            ->default('new')
                            ->options([
                                'new' => 'New',
                                'processing' => 'Processing',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'canceled' => 'Canceled',
                            ])
                            ->colors([
                                'new' => 'info',
                                'processing' => 'warning',
                                'shipped' => 'success',
                                'delivered' => 'success',
                                'canceled' => 'danger',
                            ])
                            ->icons([
                                'new' => 'heroicon-m-sparkles',
                                'processing' => 'heroicon-m-arrow-path',
                                'shipped' => 'heroicon-m-truck',
                                'delivered' => 'heroicon-m-check-badge',
                                'canceled' => 'heroicon-m-x-circle',
                            ])
                            ->required(),

                        Select::make('currency')
                            ->options([
                                'usd' => 'USD',
                                'eur' => 'EUR',
                                'gbp' => 'GBP',
                                'idr' => 'IDR',
                            ])
                            ->default('idr')
                            ->required(),

                        Select::make('shipping_method')
                            ->options([
                                'standard' => 'Standard',
                                'express' => 'Express',
                            ])
                            ->default('standard')
                            ->required(),

                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ])->columns(2),

                    Section::make('Order Items')->schema([
                        Repeater::make('orderItems')
                            ->relationship()
                            ->schema([

                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->columnSpan(4)
                                    ->reactive()
                                    ->afterStateUpdated(fn($state, Set $set) => $set('unit_amount', Product::find($state)?->price ?? 0))
                                    ->afterStateUpdated(fn($state, Set $set) => $set('total_amounth', Product::find($state)?->price ?? 0))
                                    ->required(),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->columnSpan(2)
                                    ->reactive()
                                    ->afterStateUpdated(fn($state, Set $set, Get $get) => $set('total_amounth', $state * $get('unit_amount')))
                                    ->required(),

                                TextInput::make('unit_amount')
                                    ->numeric()
                                    ->disabled()
                                    ->columnSpan(3)
                                    ->dehydrated()
                                    ->required(),

                                TextInput::make('total_amounth')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(3)
                                    ->required(),
                            ])->columns(12),

                        Placeholder::make('grand_total_placeholder')
                            ->label('Grand Total')
                            ->columnSpanFull()
                            ->content(function (Get $get, Set $set) {
                                $total = 0;

                                if (!$repeaters = $get('ordersItems')) {
                                    return $total;
                                }

                                foreach ($repeaters as $key => $repeater) {
                                    $total += $get("ordersItems.{$key}.total_amounth");
                                }
                                $set('grand_total', $total);
                                return Number::currency($total, 'IDR');
                            }),

                        Hidden::make('grand_total')
                            ->default(0)
                            ->required(),
                    ]),
                ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->searchable()
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('currency')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shipping_method')
                    ->searchable()
                    ->sortable(),

                SelectColumn::make('status')
                    ->searchable()
                    ->options([
                        'new' => 'New',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'canceled' => 'Canceled',
                    ])
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('Update Payment Invoice')
                        ->icon('heroicon-o-shopping-bag')
                        ->requiresConfirmation()
                        ->action(function (Order $record) {
                            if ($record && ($record->payment_method == 'invoice' || $record->payment_method == 'cod') && $record->status == 'new') {
                                Config::$serverKey = config('services.midtrans.server_key');
                                Config::$isProduction = config('services.midtrans.is_production');
                                Config::$isSanitized = true;
                                Config::$is3ds = true;

                                try {
                                    $checkStatus = Transaction::status($record->id);

                                    if ($checkStatus->transaction_status == 'settlement') {
                                        $record->update([
                                            'status' => 'processing',
                                            'payment_status' => 'paid'
                                        ]);
                                        Notification::make()
                                            ->title('Payment Updated')
                                            ->body('Payment updated successfully.')
                                            ->success()
                                            ->send();
                                    } else {
                                        Notification::make()
                                            ->title('Update Failed')
                                            ->body('Payment status not settled. Unable to update.')
                                            ->danger()
                                            ->send();
                                    }
                                } catch (\Exception $e) {
                                    $errorMessage = json_decode($e->getMessage(), true);
                                    if (isset($errorMessage['status_code']) && $errorMessage['status_code'] == '404') {
                                        Notification::make()
                                            ->title('Transaction Not Found')
                                            ->body("Transaction doesn't exist.")
                                            ->danger()
                                            ->send();
                                    } else {
                                        Notification::make()
                                            ->title('Transaction Not Found')
                                            ->body("Transaction doesn't exist.")
                                            ->danger()
                                            ->send();
                                    }
                                }
                            } else {
                                Notification::make()
                                    ->title('Invalid Data')
                                    ->body('Invalid payment method or record.')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('Update Payment cod')
                        ->icon('heroicon-o-shopping-bag')
                        ->requiresConfirmation()
                        ->action(function (Order $record) {
                            if ($record && $record->payment_method == 'cod' && $record->status == 'new') {
                                try {
                                    // Update the record directly since it's COD
                                    $record->update([
                                        'status' => 'delivered',
                                        'payment_status' => 'paid'
                                    ]);
                                    Notification::make()
                                        ->title('Payment Updated')
                                        ->body('Payment updated successfully.')
                                        ->success()
                                        ->send();
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('Update Failed')
                                        ->body('An error occurred: ' . $e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            } else {
                                Notification::make()
                                    ->title('Invalid Data')
                                    ->body('Invalid payment method or record.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AddressRelationManager::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return static::getModel()::count() > 10 ? 'success' : 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
