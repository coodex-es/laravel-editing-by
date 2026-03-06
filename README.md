# Laravel Editing By

Version: `1.0.0`

`coodex-es/laravel-editing-by` permite reservar temporalmente la edición de cualquier modelo Eloquent para evitar conflictos entre usuarios mientras trabajan sobre la misma ficha.

## Qué resuelve

El paquete crea un registro temporal en `model_editings` para indicar quién está editando cada modelo y hasta cuándo dura esa reserva. Sobre esa base aporta:

- consulta de si un modelo está siendo editado por otro usuario
- marcaje o renovación de la edición del usuario actual
- liberación manual de la edición
- toma de posesión de una edición activa
- joins para enriquecer listados con el editor activo
- limpieza automática de ediciones expiradas cada minuto
- eventos para reaccionar a altas, liberaciones y takeovers

## Compatibilidad

- PHP `^8.1`
- Laravel `^9.0|^10.0|^11.0|^12.0|^13.0`

## Instalación

```bash
composer require coodex-es/laravel-editing-by
php artisan migrate
```

Si durante el desarrollo lo enlazas por `path repository`, recuerda refrescar el autoload después de cambios estructurales.

## Configuración

Publica la configuración si necesitas personalizar el paquete:

```bash
php artisan vendor:publish --tag=editing-by-config
```

Opciones disponibles en `config/editing-by.php`:

- `table`: nombre de la tabla de reservas. Por defecto `model_editings`.
- `default_ttl_seconds`: duración por defecto de cada edición. Por defecto `20`.
- `user_model`: modelo de usuario que posee la edición.
- `user_table`: tabla de usuarios. Si no se define, se toma del modelo configurado.
- `user_key_name`: nombre de la clave primaria del usuario. Si no se define, se toma del modelo configurado.
- `user_key_column_type`: tipo de columna para `user_id`. Valores soportados: `auto`, `foreignId`, `unsignedBigInteger`, `uuid`, `ulid`, `string`.
- `prune_expired_schedule`: activa o desactiva la limpieza automática programada.

### Sobre `user_id`

La tabla `model_editings` se crea usando el modelo de usuario configurado en el momento de ejecutar la migración. Si en el futuro cambian la tabla de usuarios, el nombre de la PK o su tipo, debes recrear `model_editings` para mantener la FK coherente.

El paquete incluye este comando destructivo para hacerlo:

```bash
php artisan editing-by:recreate-table --force
```

## Uso básico

Añade el trait `HasEditingBy` a cualquier modelo Eloquent que quieras proteger:

```php
use CoodexEs\LaravelEditingBy\Concerns\HasEditingBy;

class Voucher extends Model
{
    use HasEditingBy;
}
```

Operaciones principales:

- `isBeingEdited()`: devuelve `true` si existe una edición activa de otro usuario.
- `markEditing()`: crea o renueva la edición del usuario actual; si otro usuario la posee, lanza `ModelIsBeingEditedException`.
- `addEditingTime()`: amplía la expiración de la edición actual del usuario autenticado.
- `releaseEditing()`: libera la edición actual del usuario autenticado.
- `takeOverEditing()`: toma posesión de la edición activa.
- `editor()`: devuelve el usuario que edita actualmente el modelo.
- `editingRecord()`: devuelve el registro activo de edición.
- `scopeWithActiveEditor()`: añade joins y aliases para enriquecer listados.

Ejemplo de uso en una pantalla de edición con polling:

```php
try {
    $voucher->markEditing();
} catch (ModelIsBeingEditedException $exception) {
    return response()->json([
        'editing' => true,
        'user' => $exception->editing->user,
    ], 423);
}
```

## Campos añadidos por `scopeWithActiveEditor()`

El scope incorpora, cuando existe una edición activa no expirada y distinta del usuario autenticado:

- `editing_by_user_id`
- `editing_by_name`
- `editing_by_surname`
- `editing_by_email`
- `locked_by`

## Eventos

El paquete despacha estos eventos estándar de Laravel:

- `EditingStarted`
- `EditingReleased`
- `EditingTakenOver`

Esto permite conectar listeners, notificaciones o broadcasting sin acoplarlo al paquete.

## Scheduler

El service provider registra automáticamente el comando:

```bash
php artisan editing-by:prune-expired
```

Si `prune_expired_schedule` está activo, el paquete programa su ejecución cada minuto.
