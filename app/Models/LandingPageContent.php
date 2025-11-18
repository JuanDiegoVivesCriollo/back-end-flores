<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageContent extends Model
{
    protected $table = 'landing_page_content';

    protected $fillable = [
        'section',
        'key',
        'value',
        'type',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope para obtener contenido activo
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para obtener contenido por sección
     */
    public function scopeBySection($query, $section)
    {
        return $query->where('section', $section);
    }

    /**
     * Obtener contenido por sección y clave
     */
    public static function getContent($section, $key, $default = '')
    {
        $content = static::where('section', $section)
                         ->where('key', $key)
                         ->where('is_active', true)
                         ->first();

        return $content ? $content->value : $default;
    }

    /**
     * Establecer contenido
     */
    public static function setContent($section, $key, $value, $type = 'text', $description = null)
    {
        return static::updateOrCreate(
            ['section' => $section, 'key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description,
                'is_active' => true
            ]
        );
    }
}
