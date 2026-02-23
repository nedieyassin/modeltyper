<?php
namespace FumeApp\ModelTyper\Actions;
use FumeApp\ModelTyper\Traits\ClassBaseName;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
class WriteRelationship
{
    use ClassBaseName;

    /**
     * Words that Str::singular() mangles and should be left as-is.
     */
    protected static array $singularExceptions = [
        'Bus'   => 'Bus',
        'bus'   => 'bus',
        'Atlas' => 'Atlas',
        'alias' => 'alias',
        'Alias' => 'Alias',
        'Gas'   => 'Gas',
        'gas'   => 'gas',
        'Lens'  => 'Lens',
        'lens'  => 'lens',
        'News'  => 'News',
        'news'  => 'news',
        'Status' => 'Status',
        'status' => 'status',
    ];

    /**
     * Safely singularize a word, falling back to the original if the
     * inflector produces a result that is clearly mangled (e.g. "Bus" -> "Bu").
     */
    protected function safeSingular(string $word): string
    {
        if (isset(static::$singularExceptions[$word])) {
            return static::$singularExceptions[$word];
        }

        $singular = Str::singular($word);

        // If the singularized word lost more than 3 characters compared to the
        // original, or no longer shares its first two characters with the
        // original, treat the result as mangled and return the original.
        if (
            strlen($singular) < strlen($word) - 3
            || !str_starts_with(strtolower($word), substr(strtolower($singular), 0, 2))
        ) {
            return $word;
        }

        return $singular;
    }

    /**
     * Write the relationship to the output.
     *
     * @param  array{name: string, type: string, related:string}  $relation
     * @return array{type: string, name: string}|string
     */
    public function __invoke(array $relation, string $indent = '', bool $jsonOutput = false, bool $optionalRelation = false, bool $plurals = false): array|string
    {
        $case = Config::get('modeltyper.case.relations', 'snake');
        $name = app(MatchCase::class)($case, $relation['name']);
        $relatedModel = $this->getClassName($relation['related']);
        $optional = $optionalRelation ? '?' : '';
        $relationType = match ($relation['type']) {
            'BelongsToMany', 'HasMany', 'HasManyThrough', 'MorphToMany', 'MorphMany', 'MorphedByMany' => $plurals === true ? Str::plural($relatedModel) : ($this->safeSingular($relatedModel) . '[]'),
            'BelongsTo', 'HasOne', 'HasOneThrough', 'MorphOne', 'MorphTo' => $this->safeSingular($relatedModel),
            default => $relatedModel,
        };

        if (in_array($relation['type'], Config::get('modeltyper.custom_relationships.singular', []))) {
            $relationType = $this->safeSingular($relation['type']);
        }
        if (in_array($relation['type'], Config::get('modeltyper.custom_relationships.plural', []))) {
            $relationType = $this->safeSingular($relation['type']);
        }
        if ($jsonOutput) {
            return [
                'name' => "{$name}{$optional}",
                'type' => $relationType,
            ];
        }
        return "{$indent}  {$name}{$optional}: I{$relationType}" . PHP_EOL;
    }
}
