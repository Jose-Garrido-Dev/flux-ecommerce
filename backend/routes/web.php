<?php

use App\Domain\Returns\Models\ReturnEvidence;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Respaldo de devoluciones: archivo privado, solo para el equipo con permiso de ver devoluciones
Route::get('/admin/respaldo-devolucion/{evidence}', function (ReturnEvidence $evidence) {
    abort_unless(auth()->user()?->can('returns.view'), 403);
    abort_unless(Storage::disk('local')->exists($evidence->path), 404);

    return Storage::disk('local')->response($evidence->path, $evidence->original_name, ['Content-Disposition' => 'inline']);
})->name('admin.return-evidence');
