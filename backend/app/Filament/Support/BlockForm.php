<?php

namespace App\Filament\Support;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Media;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\MediaUploader;
use App\Domain\Content\Services\BlockSchema;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Arma el constructor de páginas del panel a partir del esquema compartido de bloques. */
final class BlockForm
{
    public static function builder(string $name = 'blocks'): Builder
    {
        $blocks = [];
        foreach (BlockSchema::all() as $type => $def) {
            $blocks[] = Block::make($type)->label($def['label'])->icon($def['icon'] ?? null)
                ->schema(self::fields($def['fields']))->columns(2);
        }

        return Builder::make($name)->label('Bloques')->blocks($blocks)
            ->addActionLabel('Agregar bloque')->addBetweenActionLabel('Insertar bloque aquí')
            ->blockPickerColumns(3)->collapsible()->cloneable()->reorderableWithButtons()
            ->maxItems(BlockSchema::MAX_BLOCKS)
            ->blockNumbers(false)
            ->helperText('Los bloques se muestran en la tienda en este orden. Guardar crea una versión nueva; la tienda cambia solo al publicar.');
    }

    /** @return list<Field> */
    private static function fields(array $fields): array
    {
        $out = [];
        foreach ($fields as $name => $f) {
            $field = self::field($name, $f);
            if (isset($f['required_if'])) {
                [$other, $value] = [array_key_first($f['required_if']), reset($f['required_if'])];
                $field->visible(fn (Get $get) => $get($other) === $value)->required(fn (Get $get) => $get($other) === $value);
            } elseif ($f['required'] ?? false) {
                $field->required();
            }
            if (array_key_exists('default', $f)) {
                $field->default($f['default']);
            }
            $out[] = $field;
        }

        return $out;
    }

    private static function field(string $name, array $f): Field
    {
        $label = $f['label'];

        return match ($f['type']) {
            'text' => TextInput::make($name)->label($label)->maxLength($f['max'] ?? 255),
            'textarea' => Textarea::make($name)->label($label)->rows(3)->maxLength($f['max'] ?? 1000)->columnSpanFull(),
            'markdown' => MarkdownEditor::make($name)->label($label)->maxLength($f['max'] ?? 20000)->columnSpanFull()
                ->toolbarButtons([['bold', 'italic', 'link'], ['heading', 'bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']]),
            'link' => TextInput::make($name)->label($label)->maxLength(500)->placeholder('/catalogo o https://…')
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    if (filled($value) && ! BlockSchema::validLink((string) $value)) {
                        $fail('Usa una ruta de la tienda que empiece con / o una dirección https://.');
                    }
                }),
            'video' => TextInput::make($name)->label($label)->url()->maxLength(500)->placeholder('https://www.youtube.com/watch?v=…')->columnSpanFull(),
            'select' => Select::make($name)->label($label)->options($f['options'])->native(false)->selectablePlaceholder(false),
            'number' => TextInput::make($name)->label($label)->integer()->minValue($f['min'] ?? null)->maxValue($f['max'] ?? null),
            'datetime' => DateTimePicker::make($name)->label($label)->seconds(false)->native(false),
            'media' => self::media($name, $label),
            'category' => Select::make($name)->label($label)->searchable()->preload()
                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all()),
            'brand' => Select::make($name)->label($label)->searchable()->preload()
                ->options(fn () => Brand::query()->orderBy('name')->pluck('name', 'id')->all()),
            'categories' => Select::make($name)->label($label)->multiple()->searchable()->preload()->maxItems($f['max_items'] ?? 12)
                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all())->columnSpanFull(),
            'brands' => Select::make($name)->label($label)->multiple()->searchable()->preload()->maxItems($f['max_items'] ?? 24)
                ->options(fn () => Brand::query()->orderBy('name')->pluck('name', 'id')->all())->columnSpanFull(),
            'products' => Select::make($name)->label($label)->multiple()->searchable()->maxItems($f['max_items'] ?? 24)->columnSpanFull()
                ->getSearchResultsUsing(fn (string $search) => Product::query()->where('name', 'like', '%'.$search.'%')->orderBy('name')->limit(30)->pluck('name', 'id')->all())
                ->getOptionLabelsUsing(fn (array $values) => Product::query()->whereIn('id', $values)->pluck('name', 'id')->all()),
            'list' => Repeater::make($name)->label($label)->schema(self::fields($f['fields']))->columns(2)
                ->maxItems($f['max_items'] ?? 20)->collapsible()->reorderableWithButtons()->addActionLabel('Agregar')->columnSpanFull()
                ->itemLabel(fn (array $state) => $state[array_key_first($f['fields'])] ?? null),
            default => TextInput::make($name)->label($label),
        };
    }

    /** Imagen de la biblioteca: buscar una existente o subir una nueva desde el mismo campo. */
    private static function media(string $name, string $label): Select
    {
        $option = fn (Media $m) => new HtmlString('<span style="display:flex;gap:.5rem;align-items:center"><img src="'.e($m->thumbUrl()).'" alt="" style="width:2rem;height:2rem;object-fit:cover;border-radius:4px">'
            .e($m->title ?: $m->alt ?: $m->original_name ?: 'Imagen '.$m->id).'</span>');

        return Select::make($name)->label($label)->searchable()->allowHtml()
            ->getSearchResultsUsing(fn (string $search) => Media::query()
                ->where(fn ($q) => $q->where('alt', 'like', "%$search%")->orWhere('title', 'like', "%$search%")->orWhere('original_name', 'like', "%$search%"))
                ->latest('id')->limit(20)->get()->mapWithKeys(fn (Media $m) => [$m->id => (string) $option($m)])->all())
            ->options(fn () => Media::query()->latest('id')->limit(20)->get()->mapWithKeys(fn (Media $m) => [$m->id => (string) $option($m)])->all())
            ->getOptionLabelUsing(fn ($value) => ($m = Media::query()->find($value)) ? (string) $option($m) : null)
            ->createOptionForm([
                FileUpload::make('file')->label('Imagen')->image()->required()
                    ->disk('local')->directory('tmp-uploads')->storeFiles(false)
                    ->acceptedFileTypes(array_keys(MediaUploader::ALLOWED))->maxSize(MediaUploader::MAX_BYTES / 1024)
                    ->helperText('JPG, PNG, WebP o GIF hasta 10 MB. Se optimiza automáticamente.'),
                TextInput::make('alt')->label('Texto alternativo')->maxLength(250)
                    ->helperText('Describe la imagen para quienes no la ven (lectores de pantalla y buscadores).'),
            ])
            ->createOptionModalHeading('Subir imagen')
            ->createOptionUsing(function (array $data) {
                $file = $data['file'];
                $file = is_array($file) ? reset($file) : $file;
                if (! $file instanceof TemporaryUploadedFile) {
                    return null;
                }

                return app(MediaUploader::class)->store($file->getRealPath(), $file->getClientOriginalName(), $data['alt'] ?? null, 'contenido')->id;
            });
    }
}
