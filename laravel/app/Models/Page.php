<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

// Vereinheitlicht die bisherige "seiten-*"-Registry-Familie (siehe
// Analysebericht Punkt 7.2) - "parent_id" bildet ab, was heute durch
// "Datei liegt im Unterverzeichnis der Registry" ausgedrueckt wird.
#[Fillable([
    'section', 'parent_id', 'slug', 'titel', 'untertitel', 'nav_label',
    'intro', 'inhalt', 'grusswort', 'hero_bild', 'bild', 'bild_alt',
    'vorschaubild', 'kurzbeschreibung', 'bild_groesse', 'kontakt_name',
    'kontakt_email', 'kontakt_telefon',
    // Phase-3-Fix: nur bei content/jaeger/mitglied-werden.json real
    // vorhanden (Button-Link zum externen Online-Mitgliedsantrag), siehe
    // Migration 2026_09_16_000003.
    'antrag_url',
    'unterseiten_titel',
    // Phase-3-Fix: PageContentController::pageToJson() las dieses Feld
    // schon vorher aus, ohne dass die Spalte existierte (stiller
    // Datenverlust bei 31 Seiten) - siehe Migration 2026_09_16_000003.
    'galerie_titel',
    'gruppe',
    'linkliste_titel', 'hundeboerse_cta_titel', 'hundeboerse_cta_text',
    'hundeboerse_cta_button', 'bild_flat', 'in_navigation', 'veroeffentlicht',
    // Bugfix (kjs:compare-content, Werte-Vergleich): getrennt vom
    // Seiten-eigenen "veroeffentlicht" - siehe Migration
    // 2026_09_16_000004 und ImportContent::importWeitere().
    'registry_veroeffentlicht',
    'sortierung',
])]
class Page extends Model
{
    protected function casts(): array
    {
        return [
            'in_navigation' => 'boolean',
            'veroeffentlicht' => 'boolean',
            'registry_veroeffentlicht' => 'boolean',
            'bild_flat' => 'boolean',
            'sortierung' => 'integer',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /** @return HasMany<Page, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id');
    }

    /** @return HasMany<PageLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(PageLink::class);
    }

    /** @return MorphMany<GalerieBild, $this> */
    public function galerieBilder(): MorphMany
    {
        return $this->morphMany(GalerieBild::class, 'owner');
    }

    /** @return MorphMany<Download, $this> */
    public function downloads(): MorphMany
    {
        return $this->morphMany(Download::class, 'owner');
    }
}
