<?php

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Column\Models\Column;
use Modules\Customer\Repositories\CustomerRepository;
use Modules\Directory\Models\DirectoryCurrencyRate;
use Modules\Directory\Models\DirectoryCurrencySetup;
use Modules\Menu\Models\Menu;
use Nwidart\Modules\Facades\Module;

if (! function_exists('getStorageDisk')) {
    function getStorageDisk()
    {
        return config('core.aws_upload') == config('core.yes') ? 's3' : 'public';
    }
}

if (! function_exists('getImageUrl')) {
    function getImageUrl($param)
    {
        /*
      $param = [
            'image-type'    =>  'resize',//'thumbnail' = thumbnails 2) resize = resize  3) is defualt give orignal image url
            'height'        =>  100,// if pass image-type = resize then you must need to pass ((image height and width) or image-size )
            'width'         =>  100,//if pass image-type = resize then you must need to pass ((image height and width) or image-size )
            'module'        =>  'banner',//must be need to pass
            'image'         =>  'cXbgZvYcYloZ4ktT0hxnE1iBfg27Xeqt7MN5Y6ce.jpeg',
            'defualt-image' =>  true,//if you want to defualt image in case of image not found,
            'aspect-ratio' => true,//if you want to aspect ratio of image,
            'prevent-upsize' => true,//if you want to prevent possible upsizing,
            'crop-image' => true,//if you want to crop image,
            'thumbnail-size' => 100,//if you want to pass thumbnail-size by-default is 100,
            'x-coordinate' => 'X-Coordinate of the top-left corner if the rectangular cutout. By default the rectangular part will be centered on the current image. use in crop',
            'y-coordinate' => 'Y-Coordinate of the top-left corner if the rectangular cutout. By default the rectangular part will be centered on the current image. use in crop',
        ];
        */
        $path = '';

        if (empty($param['image']) || empty($param['module'])) {
            if (isset($param['defualt-image'])) {
                return getDefaultImage(isset($param['module']) && ! empty($param['module']) ? strtolower($param['module']) : null);
            }

            return null;
        }
        if (! empty($param['image-type'])) {
            if ($param['image-type'] == 'resize') {
                if (isset($param['image-size'])) {
                    $dimensionPath = imageResize($param['image'], strtolower($param['module']), isset($param['image-size']) ? $param['image-size'] : null, isset($param['image-size']) ? $param['image-size'] : null, isset($param['aspect-ratio']) ? $param['aspect-ratio'] : true, isset($param['prevent-upsize']) ? $param['prevent-upsize'] : false, isset($param['crop-image']) ? $param['crop-image'] : false);
                    $path .= $dimensionPath;
                } elseif (isset($param['height']) || isset($param['width'])) {
                    $dimensionPath = imageResize($param['image'], strtolower($param['module']), isset($param['width']) ? $param['width'] : null, isset($param['height']) ? $param['height'] : null, isset($param['aspect-ratio']) ? $param['aspect-ratio'] : true, isset($param['prevent-upsize']) ? $param['prevent-upsize'] : false, isset($param['crop-image']) ? $param['crop-image'] : false);
                    $path .= $dimensionPath;
                }
            } elseif ($param['image-type'] == 'thumbnail') {
                if (isset($param['image'])) {
                    if (! file_exists(public_path().'/storage/'.strtolower($param['module']).'/thumbnails/'.$param['image'])) {
                        $dimensionPath = imageResize($param['image'], strtolower($param['module']), isset($param['thumbnail-size']) ? $param['thumbnail-size'] : 100, isset($param['thumbnail-size']) ? $param['thumbnail-size'] : 100, isset($param['aspect-ratio']) ? $param['aspect-ratio'] : true, isset($param['prevent-upsize']) ? $param['prevent-upsize'] : false, isset($param['crop-image']) ? $param['crop-image'] : false, isset($param['x-coordinate']) ? $param['x-coordinate'] : null, isset($param['y-coordinate']) ? $param['y-coordinate'] : null, 'thumbnails');

                    }
                }
                $path .= 'thumbnails'.'/';
            }
        }
        if (isset($param['image'])) {
            $path .= $param['image'];
        }
        $imagePath = public_path().'/storage/'.strtolower($param['module']).'/'.$path;
        $imageUrl = URL::to('/').'/storage/'.strtolower($param['module']).'/'.$path;
        if (is_file($imagePath)) {
            return $imageUrl;
        } else {
            if (isset($param['defualt-image']) && $param['defualt-image']) {
                return getDefaultImage(isset($param['module']) && ! empty($param['module']) ? strtolower($param['module']) : null);
            }

            return null;
        }
    }
}

if (! function_exists('imageResize')) {
    function imageResize($fileName, $moduleName, $width = null, $height = null, $aspectRatio = false, $upsize = false, $crop = false, $xCoordinate = null, $yCoordinate = null, $folderName = null)
    {
        $sourcePath = public_path('storage').'/'.$moduleName.'/'.$fileName;
        if (! file_exists($sourcePath)) {
            return null;
        }

        /* $upsize carries the "prevent upsizing" flag, it does not mean "enlarge" */
        if ($folderName) {
            $folderPath = $folderName.'/';
        } elseif (! empty($width) && ! empty($height)) {
            $folderPath = $height.'x'.$width.'/';
        } elseif (! empty($width)) {
            $folderPath = 'w'.$width.'/';
        } elseif (! empty($height)) {
            $folderPath = 'h'.$height.'/';
        } else {
            return null;
        }

        $targetDirectory = public_path('storage').'/'.$moduleName.'/'.$folderPath;
        if (! is_dir($targetDirectory)) {
            File::makeDirectory($targetDirectory, 0777, true, true);
        }

        $width = ! empty($width) ? (int) $width : null;
        $height = ! empty($height) ? (int) $height : null;

        try {
            $img = (new ImageManager(new Driver))->decodePath($sourcePath);

            if ($crop && $width && $height) {
                $img->crop($width, $height, (int) $xCoordinate, (int) $yCoordinate);
            } elseif ($crop && $width) {
                $img->cover($width, $height ?: $width);
            } elseif ($aspectRatio && $upsize) {
                $img->scaleDown($width, $height);
            } elseif ($aspectRatio) {
                $img->scale($width, $height);
            } elseif ($upsize) {
                $img->resizeDown($width, $height);
            } else {
                $img->resize($width, $height);
            }

            $img->save($targetDirectory.$fileName);
        } catch (Throwable $e) {
            Log::error('imageResize: '.$e->getMessage(), ['file' => $sourcePath]);

            return null;
        }

        return $folderPath;
    }
}

if (! function_exists('getFormatedDate')) {
    $defaultTimeZone = '';
    function getFormatedDate($date, $format = 'Y-m-d H:i:s', $timezone = null)
    {
        global $defaultTimeZone;
        if (! $defaultTimeZone) {
            $defaultTimeZone = settings('core', 'timezone');
        }
        if (! $timezone && $defaultTimeZone) {
            $timezone = $defaultTimeZone;
        }

        return Carbon::parse($date)->setTimezone($timezone)->format($format);
    }
}

if (! function_exists('getDuration')) {
    function getDuration($startDate, $endDate, $format = 'Y-m-d H:i:s')
    {
        $start_date = Carbon::createFromFormat($format, $startDate);
        $end_date = Carbon::createFromFormat($format, $endDate);
        $different_days = $start_date->diffInDays($end_date);

        return $different_days;
    }
}

/* To Get Currency Code in Session by default display Currency is Set */
if (! function_exists('getCurrencyCode')) {
    function getCurrencyCode()
    {
        $currency_code = Session::get('currency_code');
        if (empty($currency_code)) {
            $currency_code = DirectoryCurrencySetup::where('is_display_currency', '=', config::get('core.isDisplayCurrency'))->value('code');
        }
        Session::put('currency_code', $currency_code);

        return $currency_code;
    }
}

if (! function_exists('getGridDateFormat')) {
    function getGridDateFormat()
    {
        return 'j M Y h:i A';
    }
}

if (! function_exists('getTimezoneOffset')) {
    function getTimezoneOffset()
    {
        $datefrom = Carbon::parse(date('Y-m-d H:i:s'));
        $dateTo = Carbon::parse(getFormatedDate(date('Y-m-d H:i:s')));

        return abs($datefrom->diffInSeconds($dateTo));
    }
}

if (! function_exists('getModule')) {
    function getModule($moduleName, $param = null)
    {
        $module = Module::findOrFail($moduleName);
        if ($module) {
            $data = $module->json();
            if ($param) {
                return $data->get($param);
            }

            return $data;
        }

        return null;
    }
}

if (! function_exists('getCamelCase')) {
    function getCamelCase($string, $separator = '_')
    {
        return lcfirst(str_replace($separator, '', ucwords($string, $separator)));
    }
}

if (! function_exists('getTimezoneList')) {
    function getTimezoneList($flag = false)
    {
        $timeZones = [];
        $timezoneArray = timezone_identifiers_list();
        foreach ($timezoneArray as $key => $value) {
            $timeZones[$value] = $value;
        }
        if ($flag) {
            $timeZones[''] = ' -- '.trans('core::core.labels.select').' -- ';
        }

        return $timeZones;
    }
}

if (! function_exists('getPerPageOption')) {
    function getPerPageOption()
    {
        $perPage = settings('core', 'per_page');
        $perPageSelect = ['' => ' -- '.trans('core::core.labels.select').' -- '];
        if ($perPage) {
            $perPage = array_filter(explode(',', $perPage));
            sort($perPage);
            foreach ($perPage as $value) {
                $perPageSelect[$value] = $value;
            }
        }

        return $perPageSelect;
    }
}

if (! function_exists('getStatusOption')) {
    function getStatusOption()
    {
        $statusOptions = [
            '' => ' -- '.trans('core::core.labels.select').' -- ',
            config('core.yes') => trans('core::core.options.yesno.yes'),
            config('core.no') => trans('core::core.options.yesno.no'),
        ];

        return $statusOptions;
    }
}

if (! function_exists('viewPasswordOption')) {
    function viewPasswordOption()
    {
        $viewPasswordOptions = [
            config('core.on') => trans('core::core.options.onoff.on'),
            config('core.off') => trans('core::core.options.onoff.off'),
        ];

        return $viewPasswordOptions;
    }
}

if (! function_exists('getDefaultImage')) {
    function getDefaultImage($moduleName = null, $fileName = null)
    {
        $moduleName = strtolower($moduleName);
        $fileName = empty($fileName) ? 'default.png' : $fileName;
        if (file_exists(public_path().'/modules/'.$moduleName.'/'.$fileName)) {
            return URL::to('/').'/modules/'.$moduleName.'/'.$fileName;
        } else {
            return URL::to('/').'/modules/core/default/default.png';
        }
    }
}

if (! function_exists('getFormatedImageType')) {
    function getFormatedImageType($imageTypesArray)
    {
        $mimeTypesArray = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'txt' => 'text/plain',
            'xls' => 'application/vnd.ms-excel',
            'csv' => '.csv',
            'doc' => 'application/msword',
            'exe' => 'application/octet-stream',
            'gif' => 'image/gif',
            'html' => 'text/html',
            'js' => 'application/x-javascript',
            'json' => 'application/json',
            'pdf' => 'application/pdf',
            'ppt' => 'application/vnd.ms-powerpoint',
            'css' => 'text/css',
        ];
        if (! is_array($imageTypesArray)) {
            $imageTypesArray = explode(',', $imageTypesArray);
        }
        foreach ($imageTypesArray as $key => $value) {
            $mimesTypes[] = $mimeTypesArray[$value];
        }

        return $mimesTypes;
    }
}
if (! function_exists('getMonthOptions')) {
    function getMonthOptions($flag = false)
    {
        $options = [];
        for ($i = 1; $i <= 12; $i++) {
            $options[$i] = date('F', strtotime('01.'.$i.'.'.date('Y')));
        }

        return $options;
    }
}

if (! function_exists('getConvertedCurrency')) {
    function getConvertedCurrency($price, $currency)
    {
        if ($baseCurrency = DirectoryCurrencySetup::where('is_base_currency', '=', config::get('core.isBaseCurrency'))->value('code')) {
            if ($baseCurrency == $currency) {
                return $price;
            }
            if ($currency_rate = DirectoryCurrencyRate::where('currency_from', '=', $baseCurrency)->where('currency_to', '=', $currency)->value('rate')) {
                return round($price * $currency_rate, 2);
            }
        }

        return null;
    }
}

if (! function_exists('replaceUrl')) {
    /**
     *  Replace url
     *
     * @param  $content
     *                  return url
     */
    function replaceUrl($content)
    {
        preg_match_all('/href=\"([^\"]+)\"/', $content, $match);
        if (! empty($match[1])) {
            foreach (array_unique($match[1]) as $urlKey) {
                if ($urlKey) {
                    $content = str_replace($urlKey, URL::to('/'.$urlKey), $content);
                    if (config('core.translation_front')) {
                        $content = str_replace($urlKey, URL::to('/'.app()->getLocale().'/'.$urlKey), $content);
                    }
                }
            }
        }

        return $content;
    }
}

if (! function_exists('replaceImageUrl')) {
    /**
     *  Replace Image url
     *
     * @param  $content
     *                  return url
     */
    function replaceImageUrl($content)
    {
        preg_match_all('/src=\"([^\"]+)\"/', $content, $match);
        if (! empty($match[1])) {
            foreach (array_unique($match[1]) as $urlKey) {
                if ($urlKey) {
                    $content = str_replace($urlKey, asset($urlKey), $content);
                }
            }
        }

        return $content;
    }
}

if (! function_exists('getImageByParticularDimension')) {
    /**
     *  resize images with particular dimension and store with same name which is stored in database
     *
     * @param  $content
     */
    function getImageByParticularDimension($file, $module, $width = '100', $height = '100', $perfect_height = false)
    {
        $sizePath = '/'.$module.'/'.$height.'x'.$width.'/';
        if (! Storage::disk('public')->exists($sizePath)) {
            File::makeDirectory(public_path('storage'.$sizePath), 0777, true);
        }
        if ($perfect_height == true) {
            $resizeImg = Image::make($file)->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
            })->save();
        } else {
            $resizeImg = Image::make($file)->resize($width, $height)->save();
        }
        if (isset($resizeImg) && ! empty($resizeImg)) {
            $filename = $resizeImg->filename.'.'.$resizeImg->extension;
            Storage::put('public'.$sizePath.$filename, $resizeImg->__toString());
            $storageImagePath = '/storage'.$sizePath.$filename;
        }
    }
}

if (! function_exists('getImageByParticularDimension_new')) {
    /**
     *  resize images with particular dimension and store with same name which is stored in database
     *
     *  Kept as a thin wrapper over imageResize() for backwards compatibility.
     */
    function getImageByParticularDimension_new($file, $module, $width = '100', $height = '100', $folderName = null)
    {
        return imageResize($file, strtolower($module), $width, $height, true, false, false, null, null, $folderName);
    }
}

if (! function_exists('getLastMonths')) {
    function getLastMonths($number = false)
    {
        if (! $number) {
            $number = 3;
        }
        $options = [];
        for ($i = 1; $i <= $number; $i++) {
            $options[$i] = date('F Y', strtotime('-'.$i.'month'));
        }

        return $options;
    }
}

/**
 *  Function to encrypt Data
 *
 *  @param  SrcPathFoder
 * @return id
 */
if (! function_exists('encrypt_data')) {

    function encrypt_data($string)
    {
        $encrypter = app('Illuminate\Contracts\Encryption\Encrypter');
        $encrypted = $encrypter->encrypt($string);

        return $encrypted;
    }
}

if (! function_exists('encryptPassword')) {

    function encryptPassword($password)
    {
        return hash('sha256', md5($password));
    }
}

/**
 *  Function to decrypt Data
 *
 *  @param  SrcPathFoder
 * @return none
 */
if (! function_exists('decrypt_data')) {
    function decrypt_data($encrypted_string)
    {
        $encrypter = app('Illuminate\Contracts\Encryption\Encrypter');
        $decrypted = $encrypter->decrypt($encrypted_string);

        return $decrypted;
    }
}
/**
 *  Function to wordWrapper
 *
 *  @param  SrcPathFoder
 * @return none
 */
// if (!function_exists('wordWrapper')) {
//     function wordWrapper($str)
//     {
//         echo wordwrap($str, 15, "<br>\n");
//     }
// }
/**
 *  Function to encrypt It
 *
 *  @param  SrcPathFoder
 * @return none
 */
if (! function_exists('encrypt_It')) {
    function encrypt_It($plaintext)
    {
        $cipher = Config::get('core.encrypt.method');
        $password = Config::get('core.encrypt.password');
        $iv = normalizeEncryptIv(Config::get('core.encrypt.iv'), $cipher);
        $ciphertext = base64_encode(openssl_encrypt((string) $plaintext, $cipher, $password, $options = 0, $iv));

        return $ciphertext;
    }
}
/**
 *  Function to decrypt It
 *
 *  @param  SrcPathFoder
 * @return none
 */
if (! function_exists('decrypt_It')) {
    function decrypt_It($encrypted)
    {
        $cipher = Config::get('core.encrypt.method');
        $password = Config::get('core.encrypt.password');
        $iv = normalizeEncryptIv(Config::get('core.encrypt.iv'), $cipher);
        $decrypted = openssl_decrypt(base64_decode((string) $encrypted), $cipher, $password, $options = 0, $iv);

        return $decrypted;
    }
}

if (! function_exists('normalizeEncryptIv')) {
    /**
     * Pad or trim the configured iv to the length the cipher expects, so a
     * mismatch raises no warning (which Laravel would turn into an exception).
     */
    function normalizeEncryptIv($iv, $cipher)
    {
        $length = openssl_cipher_iv_length($cipher);

        if (! $length) {
            return '';
        }

        return substr(str_pad((string) $iv, $length, '0'), 0, $length);
    }
}

if (! function_exists('aesEncryptData')) {
    function aesEncryptData($key)
    {
        $method = Config::get('core.aes_encrypt.method');
        $tag = '';
        $iv = generateRandomString(12, false, 'd');
        $password = generateRandomString(16, false, 'ldu');
        $encryptedPassword = base64_encode($password);
        $length = strlen($encryptedPassword);

        $ciphertext = openssl_encrypt($key, $method, $password, OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv.$length.$encryptedPassword.$tag.$ciphertext);
    }
}

if (! function_exists('aesDecryptData')) {
    function aesDecryptData($key)
    {
        $method = Config::get('core.aes_encrypt.method');
        $decoded = base64_decode($key);

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $length = (int) (substr($decoded, 12, 2));
        $password = base64_decode(substr($decoded, 14, $length));
        $length = $length + 14;
        $tag = substr($decoded, $length, 16);
        $length = $length + 16;
        $ciphertext = substr($decoded, $length);

        $decodedMessage = openssl_decrypt($ciphertext, $method, $password, OPENSSL_RAW_DATA, $iv, $tag);

        return $decodedMessage;
    }
}

if (! function_exists('shaEncryption')) {
    function shaEncryption($text)
    {
        return hash('sha256', $text);
    }
}

if (! function_exists('customAsset')) {

    function customAsset($path, $version = false, $secure = null)
    {

        if ($version) {

            if (file_exists(public_path().'/'.$path)) {
                $v = filemtime(public_path().'/'.$path);

                return asset($path, $secure).'?v='.$v;
            }
        }

        return asset($path, $secure);
    }
}

if (! function_exists('updateUrlParams')) {
    function updateUrlParams($params = [])
    {
        $urlParam = [];
        if (! empty($params) && array_key_exists('type', $params) && $params['type'] == config('core.route_type')) {
            unset($params['type']);
            if (config('core.translation_front')) {
                if (! empty($params)) {
                    $urlParam[] = app()->getLocale();
                    foreach ($params as $param) {
                        if (! is_array($param)) {
                            $urlParam[] = $param;
                        } else {
                            foreach ($param as $key => $value) {
                                $urlParam[$key] = $value;
                            }
                        }
                    }
                } else {
                    $urlParam = ['locale' => app()->getLocale()];
                }

                // dd($urlParam);
                return $urlParam;
            } else {
                if (! empty($params)) {
                    foreach ($params as $param) {
                        if (! is_array($param)) {
                            $urlParam[] = $param;
                        } else {
                            foreach ($param as $key => $value) {
                                $urlParam[$key] = $value;
                            }
                        }
                    }
                }

                return $urlParam;
            }
        } else {
            if (config('core.translation')) {
                if (! empty($params)) {
                    $urlParam[] = app()->getLocale();
                    foreach ($params as $param) {
                        if (! is_array($param)) {
                            $urlParam[] = $param;
                        } else {
                            foreach ($param as $key => $value) {
                                $urlParam[$key] = $value;
                            }
                        }
                    }
                } else {
                    $urlParam = ['locale' => app()->getLocale()];
                }

                return $urlParam;
            } else {
                if (! empty($params)) {
                    foreach ($params as $param) {
                        if (! is_array($param)) {
                            $urlParam[] = $param;
                        } else {
                            foreach ($param as $key => $value) {
                                $urlParam[$key] = $value;
                            }
                        }
                    }
                }

                return $urlParam;
            }
        }
    }
}

if (! function_exists('getPerPageForModule')) {
    function getPerPageForModule($key, $perPage = null)
    {
        $coreSettings = settings('core');
        $defaultPage = ! empty($coreSettings['default_per_page']) ? $coreSettings['default_per_page'] : '';
        if (isset($key) && ! empty($key)) {
            $key = strtolower($key);
            if (empty(Session::get('page.page_limit.'.$key)) && $perPage == null) {
                Session::put('page_limit.'.$key, $defaultPage);
                Session::put('page', ['page_limit' => Session::get('page_limit')]);
            } elseif ($perPage != null) {
                Session::put('page_limit.'.$key, $perPage);
                Session::put('page', ['page_limit' => Session::get('page_limit')]);
            }
            if (empty(Session::get('page'))) {
                Session::put('page_limit.'.$key, $defaultPage);
                Session::put('page', ['page_limit' => Session::get('page_limit')]);
            }
            $perPageSetting = ! empty($coreSettings['per_page']) ? $coreSettings['per_page'] : '';
            if (isset($perPageSetting) && ! empty($perPageSetting)) {
                $perPageSetting = array_filter(explode(',', $perPageSetting));
                sort($perPageSetting);
                if (isset($perPageSetting) && ! empty($perPageSetting) && ! in_array(Session::get('page.page_limit.'.$key), $perPageSetting)) {

                    Session::put('page_limit.'.$key, $defaultPage);
                    Session::put('page', ['page_limit' => Session::get('page_limit')]);
                }
            }

            return Session::get('page.page_limit.'.$key);
        }

        return $defaultPage;
    }
}

if (! function_exists('wordWrapper')) {
    function wordWrapper($str, $quote = false, $length = 50, $symbol = '...', $wordbreak = false)
    {
        if ($wordbreak) {
            echo wordwrap($str, $length, "<br>\n");

            return false;
            exit();
        }
        if (! empty($str) && strlen($str) >= $length) {
            if ($quote) {
                echo "<span class='title' data-placement='bottom' data-toggle='tooltip' title='$str'>".'"'.mb_substr(strip_tags($str), 0, $length).$symbol.'"'.'</span>';

                return false;
                exit();
            }
            echo "<span class='title' data-placement='bottom' data-toggle='tooltip' title='$str'>".mb_substr(strip_tags($str), 0, $length).$symbol.'</span>';

            return false;
            exit();
        } elseif (! empty($str) && strlen($str) < $length) {
            if ($quote) {
                echo '"'.strip_tags($str).'"';

                return false;
                exit();
            }
            echo strip_tags($str);

            return false;
            exit();
        }
        echo '';

        return false;
        exit();
    }

    // get primary key value
    if (! function_exists('getIncrementedValue')) {
        function getIncrementedValue($tableName)
        {
            $databaseName = DB::connection()->getDatabaseName();
            $result = DB::select(DB::raw("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA ='$databaseName' AND TABLE_NAME = '$tableName'"));

            return $result[0]->AUTO_INCREMENT;
        }
    }

    if (! function_exists('escapeHtml')) {
        /**
         * Strip HTML from a value, or recursively from every value in an array.
         *
         * $ignoreFields lists keys that must keep their raw value (rich text such
         * as a summernote body). It extends - and does not replace - the global
         * "escape_html_ignore_column" core setting.
         */
        function escapeHtml($data, $ignoreFields = [])
        {
            if (! $data) {
                return null;
            }

            $settingString = trim((string) settings('core', 'escape_html_ignore_column'));
            $settingString = str_replace(' ', '', $settingString);
            $settingsArray = array_filter(explode(',', $settingString));

            $ignore = array_merge($settingsArray, (array) $ignoreFields);

            if (is_array($data)) {
                // Built fresh at every level: seeding it with the parent's result
                // made nested arrays inherit their siblings' keys.
                $result = [];

                foreach ($data as $key => $field) {
                    if (is_array($field)) {
                        $result[$key] = escapeHtml($field, $ignoreFields);
                    } elseif (in_array($key, $ignore, true) || empty($field)) {
                        $result[$key] = $field;
                    } else {
                        $result[$key] = strip_tags(htmlspecialchars_decode($field));
                    }
                }

                return $result;
            }

            return strip_tags(htmlspecialchars_decode($data));
        }
    }
}

if (! function_exists('getStatusLabelClass')) {
    function getStatusLabelClass($status)
    {
        $statusCls = '';
        switch ($status) {
            case config('core.disabled'):
                $statusCls = 'status--denied';
                break;
            case config('core.enabled'):
                $statusCls = 'status--process';
                break;
            default:
                $statusCls = 'status-info';
                break;
        }

        return $statusCls;
    }
}

// convert date timezone and format
if (! function_exists('convertDateTimezone')) {
    function convertDateTimezone($date, $timezone, $inputDateFormat = 'd-m-Y h:i A', $outputDateFormat = 'Y-m-d H:i:s')
    {
        if (isset($date) && ! empty($date) && isset($timezone) && ! empty($timezone)) {
            return (new DateTime(date_format(date_create_from_format($inputDateFormat, $date), $outputDateFormat)))->setTimezone(new DateTimeZone($timezone))->format($outputDateFormat);
            exit();
        }

        return null;
        exit();
    }
}

if (! function_exists('setFilterSession')) {
    function setFilterSession($moduleName, $request, $key = null, $sessionKey = null)
    {
        app(CustomerRepository::class)->flushCache($moduleName);
        $filterSessionKey = ! empty($sessionKey) ? strtolower($sessionKey) : strtolower($moduleName);
        if (empty($key) && $request instanceof Request && in_array('filters', $request->segments()) && isset($moduleName) && ! empty($moduleName) && isset($request) && ! empty($request->all())) {
            Session::put($filterSessionKey.'_filter', $request->all());
        } elseif (! empty($key) && isset($moduleName) && ! empty($moduleName) && isset($request) && ! empty($request) && ! is_object($request)) {
            Session::put($filterSessionKey.'_filter.'.$key, $request);
        }
    }
}

if (! function_exists('getSessionFilter')) {
    function getSessionFilter($sessionKey, $key = null)
    {
        if (isset($sessionKey) && ! empty($sessionKey)) {
            if (! empty($key)) {
                return Session::get(strtolower($sessionKey).'_filter.'.$key);
            }

            return Session::get(strtolower($sessionKey).'_filter');
        }
    }
}

if (! function_exists('updateSessionFilterPage')) {
    function updateSessionFilterPage($sessionKey, $collection, $perPage)
    {
        if (empty($perPage) && $perPage <= 0) {
            $perPage = 20;
        }
        $collectionCount = $collection->count();

        $lastPage = (int) ceil($collectionCount / $perPage);

        if (getSessionFilter($sessionKey, 'page') > $lastPage) {
            setFilterSession($sessionKey, $lastPage, 'page');
        }
    }
}

if (! function_exists('getHeaderNote')) {

    function getHeaderNote($header = [], $otherNotes = [])
    {

        if (! empty($header)) {

            $list = '';
            foreach ($header as $key => $value) {
                $column = ucfirst(str_replace('_', ' ', $value));
                if ($key == count($header) - 1) {
                    $list = $list.'<li>'.$column.'</li>';
                } else {
                    $list = $list.'<li>'.$column.',</li>';
                }
            }

            $finalNote = '';

            if (isset($otherNotes) && ! empty($otherNotes)) {

                $otherNoteList = '';

                foreach ($otherNotes as $note) {
                    $otherNoteList = $otherNoteList.'<li>'.$note.'</li>';
                }

                $otherNoteUl = "<span><h6 class='mt-4'>".trans('core::core.labels.note')."</h6></span>
                                <ul style='margin-top:8px' class='small'>".$otherNoteList.'</ul>';

                $finalNote = $otherNoteUl;
            }

            $output = "<div class='image-note'>
                        <span><b>".trans('core::core.import_csv_modal.mentioned_headers')."</b></span>
                        <ul style='margin-top:8px' class='import-note'>
                        ".$list.'
                        </ul>
                        '.$finalNote.'
                        </div>';

            echo $output;
        }
    }
}
// get column object
if (! function_exists('getColumnObject')) {
    function getColumnObject()
    {
        return new Column;
    }
}

// get the menu id the grid columns belong to
if (! function_exists('getActiveMenuId')) {
    /**
     * Ajax grid refreshes (status change, filters, pagination) do not always carry
     * "active_menu_id", so fall back to the menu pointing at the grid route,
     * otherwise the grid would render without any column.
     *
     * @param  Request|null  $request
     * @param  string|null  $menuRouteName  route the menu links to, defaults to the current route
     * @return int|null
     */
    function getActiveMenuId($request = null, $menuRouteName = null)
    {
        $activeMenuId = ($request instanceof Request) ? $request->get('active_menu_id') : null;
        if (! empty($activeMenuId)) {
            return $activeMenuId;
        }
        if (empty($menuRouteName)) {
            $menuRouteName = request()->route()?->getName();
        }

        return Menu::where('link', $menuRouteName)->value('id');
    }
}

if (! function_exists('changeKeyToIndex')) {
    function changeKeyToIndex($array)
    {
        $numberCheck = false;
        foreach ($array as $k => $val) {
            if (is_array($val)) {
                $array[$k] = changeKeyToIndex($val);
            } // recurse
            if (is_numeric($k)) {
                $numberCheck = true;
            }
        }
        if ($numberCheck === true) {
            return array_values($array);
        } else {
            return $array;
        }
    }
}
