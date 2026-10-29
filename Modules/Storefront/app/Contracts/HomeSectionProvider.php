<?php

declare(strict_types=1);

namespace Modules\Storefront\Contracts;

/**
 * A home page section type owned by another module (e.g. "flash_deal", M18). Tag the provider
 * with HomePageBuilder::SECTION_PROVIDER_TAG and add its type to config
 * storefront.home_section_types; the builder renders view() with data() + ['section' => ...]
 * and caches the fragment like the built-in types.
 */
interface HomeSectionProvider
{
    public function type(): string;

    /**
     * The Blade view rendering the section.
     */
    public function view(): string;

    /**
     * View data, or null to hide the section.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    public function data(array $config): ?array;
}
