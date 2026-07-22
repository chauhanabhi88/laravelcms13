@if((count($languageOptions)) > 1)
<?php $prefix = isset($prefix) ? $prefix . "_" : ""; ?>
<?php if ($languageOptions): ?>
<ul class="nav nav-tabs" id="custom-tabs-three-tab" blog="tablist">
    <?php $i = 0; ?>
    <?php foreach ($languageOptions as $locale => $language): ?>
        <?php $i ++; ?>
        <?php foreach ($errors->getMessages() as $field => $messages): ?>
            <?php if (substr($field, 0, strpos($field, ".")) == $locale) $class = ' has-error' ?>
        <?php endforeach ?>
        <li class="nav-item">
            <a class="nav-link {{ App::getLocale() == $locale ? 'active' : '' }}" href="#tab_{{ $i }}" data-toggle="tab">{{ $language }}</a>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
@endif