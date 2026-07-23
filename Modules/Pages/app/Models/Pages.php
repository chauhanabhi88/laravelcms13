<?php

namespace Modules\Pages\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Block\Models\Block;
use Modules\Core\Repositories\Transaltion\Translatable;

class Pages extends Model
{
    use SoftDeletes, Translatable;

    protected $translationForeignKey = 'page_id';

    protected $table = 'pages';

    protected $fillable = ['slug', 'status'];

    public $translatedAttributes = ['title', 'body', 'meta_title', 'meta_description'];

    public function getPageBody()
    {
        return $this->_replaceBlock();
    }

    protected function _replaceBlock()
    {
        $string = $this->replaceMedia();
        preg_match_all("/##block::([-a-zA-Z_\x7f-\xff][-a-zA-Z0-9_\x7f-\xff]*)##/", $string, $matches);
        $block = new Block;
        foreach ($matches[0] as $key => $var_name) {
            if (! isset($GLOBALS[$matches[1][$key]])) {
                $blockKey = $matches[1][$key];
                $blockData = $block->where('slug', $blockKey)->first();
                $GLOBALS[$matches[1][$key]] = ($blockData ? $blockData->content : $matches[1][$key]);
            }
            $string = str_replace($var_name, $GLOBALS[$matches[1][$key]], $string);
        }
        $string = replaceImageUrl($string);

        return $string;
    }

    protected function replaceMedia()
    {
        $publicPath = public_path('Modules/CmsPages');

        return str_replace('##media##', $publicPath, $this->body);
    }
}
