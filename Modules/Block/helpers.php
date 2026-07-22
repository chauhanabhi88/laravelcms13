<?php

use Modules\Block\Repositories\BlockRepository;

if(!function_exists('getBlockContent'))
{
    /**
     * Get Block Content
     * 
     * @param $slug
     * @param $locale
     * return content
     */
    function getBlockContent($slug, $locale)
    {
        $blockRepo = app(BlockRepository::class);
        $block = $blockRepo->getBlockContent($slug, $locale);
        $content = "";
        if($block) {
            $pTag = trim(str_replace('<p>', '', trim($block->content)));
            $pEndTag = trim(str_replace('</p>', '', trim($pTag)));
            $brTag = trim(str_replace('<br>', '', trim($pEndTag)));
            $nbspTag = trim(str_replace('&nbsp;', '', trim($brTag)));
            if(!empty($nbspTag)) {
                $content = replaceUrl($block->content);
                $content = replaceImageUrl($content);
            }
        }
        return $content;
    }
}