<?php

namespace Modules\Directory\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Directory\Models\DirectoryCurrencySetup;
use Modules\Directory\Repositories\DirectoryCurrencySetupRepository;
use Modules\Directory\Repositories\DirectoryCurrencyRateRepository;
use Modules\Directory\Http\Requests\CreateRequest;
use Modules\Directory\Http\Requests\UpdateRequest;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Directory\Models\DirectoryCurrencyRate;
use Illuminate\Support\Facades\Validator;
use Modules\Directory\Http\Requests\CurrencyRequest;

class IndexController extends BackendController
{
    /**
     * @var LanguageRepository
     */
    protected $currecnySetup;

    /**
      * @var UserEntity
     */
    protected $currecnySetupEntity;

    public function __construct(DirectoryCurrencySetupRepository $currencySetup, DirectoryCurrencySetup $currecnySetupEntity,DirectoryCurrencyRateRepository $currencyRate)
    {
        parent::__construct();
        $this->currencySetup = $currencySetup;
        $this->currencyRate = $currencyRate;
        $this->currecnySetupEntity = $currecnySetupEntity;
        
    }
    /**
     * Display a listing of the resource.
      * @return Response
     */
    public function index(Request $request)
    {
        try
        {
            $this->_assetManager->addAsset("modules/theme/backend/select2/css/select2.min.css");
            $this->_assetManager->addAsset("modules/theme/backend/select2-bootstrap4-theme/select2-bootstrap4.min.css");
            $this->_assetManager->addAsset("modules/theme/backend/select2/js/select2.full.min.js");
            $currencyOptions = $this->currencySetup->getCurrencyOptions();
            $baseCurrencyRow = $this->currencySetup->getBaseCurrency();
            $baseCurrency = "";
            $baseCurrencyLabel = "";
            if(!empty($baseCurrencyRow)) {
                $baseCurrency = $baseCurrencyRow->code;
                $baseCurrencyLabel = $baseCurrencyRow->label;
            }
            $displayCurrencyRow = $this->currencySetup->getDisplayCurrency();
            $displayCurrencyLabel = !empty($displayCurrencyRow->label) ? $displayCurrencyRow->label : "";
            $allowedCurrencies = $this->currencySetup->getAllowedCurrencies();
            $currencyData = $this->currencySetup->getAllowedCurrenciesRow();
            $rateData = $this->currencyRate->getAllowedCurrenciesRate();

            return view('directory::backend.currency_setup.index', compact('request', 'currencyOptions','baseCurrency','allowedCurrencies','currencyData','rateData','baseCurrencyLabel','displayCurrencyLabel'));
        }
        catch (\Throwable $e) {
            return redirect()->route('admin.directory.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Save a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function save(CurrencyRequest $request)
    {
        try
        {
            $params = $request->all();
            $currencyModel = new DirectoryCurrencySetup();
            if(isset($params['currencySetup']))
            {
                if(isset($params['currencySetup']['currency']) && isset($params['currencySetup']['base_currency']) && isset($params['currencySetup']['display_currency']))
                {
                    $currencyModel->whereIn('label',$params['currencySetup']['currency'])->update(['is_allowed_currency' => 1]);
                    $currencyModel->whereNotIn('label',$params['currencySetup']['currency'])->update(['is_allowed_currency' => 2]);
                    foreach($params['currencySetup']['currency'] as $currency)
                    {
                        // echo "<pre>";print_r();die;
                        if($currencyRow = $currencyModel->where('label','=',$currency)->first())
                        {

                            if($params['currencySetup']['base_currency'] == $currencyRow->label)
                            {
                                $currencyModel->where('id','!=',$currencyRow->id)->update(['is_base_currency' => config("core.no")]);
                                $currencyRow->is_base_currency = config("core.yes");
                            }
                            if($params['currencySetup']['display_currency'] == $currencyRow->label)
                            {
                                $currencyModel->where('id','!=',$currencyRow->id)->update(['is_display_currency' => config("core.no")]);
                                $currencyRow->is_display_currency = config("core.yes");
                            }
                            $currencyRow->save();
                     }
                    }                
                 }
             }
             if(isset($params['symbol']))
             {
                foreach($params['symbol'] as $key => $value)
                {
                    if($currencyRow = $currencyModel->where('label','=',$key)->first())
                    {
                        if($key == $currencyRow->label)
                        {
                            $currencyRow->symbol = $value;
                        }
                        $currencyRow->save();
                    }
                }
             }
             if(isset($params['rate']))
             {
                $currencyRateModel = new DirectoryCurrencyRate();
                $baseCurrencyCode = $currencyModel->where('is_base_currency','=',config("core.yes"))->value('code');
                foreach($params['rate'] as $key => $value)
                {
                   $currencyRateModel->updateOrCreate(
                        [
                            'currency_from' => $baseCurrencyCode,
                            'currency_to' => $key,
                        ],
                        [
                            'currency_from' => $baseCurrencyCode,
                            'currency_to' => $key,
                            'rate' => $value,
                        ]
                    );
                }
            }
            flushDirectoryCache();
            return redirect(route("admin.directory.index", updateUrlParams()))->with("success", trans("directory::directory.messages.currency_updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.directory.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }

 
}
