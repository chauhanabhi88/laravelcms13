<?php

namespace Modules\ImportReport\Repositories\Eloquent;
//ini_set('memory_limit','5024M');
ini_set('max_execution_time',9000);
ini_set('memory_limit','3024M');

require app_path().'/../vendor/autoload.php';
putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path().'/test123-363908-3fc335f53ff7.json');

use Illuminate\Http\Request;
use Modules\ImportReport\Repositories\ImportReportRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\ImportReport\Repositories\Repository;
use Modules\ImportReport\Entities\ImportReport;
use Modules\ImportReport\Entities\CompetitorMapping;
use Modules\ImportReport\Entities\PrisyncVerticalReport;
use Illuminate\Support\Facades\DB;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\Core\ExponentialBackoff;
use Illuminate\Support\Facades\File;
use Modules\ImportReport\Jobs\ProcessCompetitorUpload;
use Modules\ImportReport\Jobs\ProcessPrisyncUpload;

class EloquentImportReportRepository extends EloquentBaseRepository implements ImportReportRepository
{
    public function sortColumns()
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id",
                "default_sort" => true,
            ],

            [
                "title" => trans("core::core.titles.created_at"),
                "column" => "created_at"
            ]
        ];

        if($this->getAuthUser()->can("admin.importreport.mass_delete")) {
            $massDeleteCheckbox = [
                "column" => "massDelete",
                "type" => "massDelete",
            ];
            array_unshift($columns, $massDeleteCheckbox);
        }

        return $columns;
    }

    public function getFilters($request)
    {
        $fields =  [
            [
                "type" => "number_range",
                "name" => ["from", "to"],
                "row"  => "1",
                "value" => [
                    "from" => $request->get("from", getSessionFilter(config("importreport.cache.name"), "from")),
                    "to"   => $request->get("to", getSessionFilter(config("importreport.cache.name"), "to"))
                ],
                "options" => [
                    'from' => ["label"=> trans('core::core.labels.id'),'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'to'   => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],

           [
                'type' => 'date_range',
                'name' => ["created_at_from", "created_at_to"],
                "row"  => "1",
                'value' => [
                    "created_at_from" => $request->get("created_at_from", getSessionFilter(config("importreport.cache.name"), "created_at_from")),
                    "created_at_to" => $request->get("created_at_to", getSessionFilter(config("importreport.cache.name"), "created_at_to"))
                ],
                'options' => [
                    "created_at_from" => ["label"=> trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    "created_at_to" => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control']
                ]
            ],
            [
                "type" => "action",
                "class" => "col-action",
                "row"   => "3",
                "buttons" => [
                    "submit" => [
                        "name" => "search",
                        "type" => "submit",
                        "onclick" => "searchFilter(); return false;",
                        "class" => "btn btn-info btn-flat",
                        "title" => trans('core::core.buttons.search')
                    ],
                    "reset" => [
                        "name" => "reset",
                        "type" => "button",
                        "onclick" => "window.location.href= '".route("admin.reset_filter", updateUrlParams([config("importreport.cache.name")]))."'",
                        "class" => "btn btn-secondary btn-flat",
                        "title" => trans('core::core.buttons.reset')
                    ]
                ]
            ]
        ];



        return $fields;
    }

    public function export(Request $request)
    {
        $timezoneOffset = getTimezoneOffset();

        $competitorMapping = new CompetitorMapping;
        $prisyncVerticalReport = new PrisyncVerticalReport;

       
        $collection = DB::table($prisyncVerticalReport->getTable().' AS prisyncTable')
        ->join($competitorMapping->getTable().' As competitorTable','prisyncTable.product_code', '=', 'competitorTable.mpn')
        ->select('product_name','product_code','brand','price','prisyncTable.site')
        //->selectRaw('group_concat(distinct(competitorTable.ref_url)) as ref_url')
        ->where('prisyncTable.site','!=',null)
        ->whereNotExists(function($query){
            $query->select(DB::raw(1))
                  ->from('competitor_mapping')
                  ->whereRaw('prisyncTable.site = competitor_mapping.ref_url');
        })
        ->groupBy('prisyncTable.product_code')
        ->get();
        

        // /dd($collection->toSql());
        //$url = $collection->pluck('ref_url');

        $data = [];
        if (count($collection) > 0) {
            foreach ($collection as $report) {
               // if(!in_array($report->site , $competitorUrls->toArray())){
                    $data[] = [
                        'product_name'        =>  $report->product_name,
                        'product_code'    =>  $report->product_code,
                        'brand'              =>  $report->brand,
                        'price'             =>  $report->price,
                        'site'             =>  $report->site,
                        //'ref_url'             =>  $report->ref_url,
                    ];
                //}
            }
        }

        //dd($data);
        return $data;
    }
    /*public function competitorImport(Request $request)
    {
        $file = $request->file('competitorMapping');
        $filePath = $file->getRealPath();
    
        $projectId = 'test123-363908';
        $datasetId = 'demo_dataset';
        $tableId = 'demoTest';

        $bigQuery = new BigQueryClient([
            'projectId' => $projectId,
        ]);

        //dd($bigQuery);

        $dataset = $bigQuery->dataset($datasetId);
        $table = $dataset->table($tableId);

        $schema = [
            'fields' => [
                ['name' => 'sku', 'type' => 'string'],
                ['name' => 'ref_sku', 'type' => 'string'],
                ['name' => 'ref_url', 'type' => 'string'],
                ['name' => 'ref_name', 'type' => 'string'],
                ['name' => 'ref_product_exsits', 'type' => 'integer'],
                ['name' => 'ignor', 'type' => 'integer'],
                ['name' => 'send_in_feed', 'type' => 'integer'],
                ['name' => 'priority', 'type' => 'integer'],
                ['name' => 'piece_multiplier', 'type' => 'float'],
                ['name' => 'piece_count', 'type' => 'integer'],
                ['name' => 'shipping_method', 'type' => 'string'],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'brand_value', 'type' => 'string'],
                ['name' => 'mpn', 'type' => 'string'],
            ]
        ];

        //$schema = ['fields' => $fields]; // For Specifie Fields

        //$table = $dataset->createTable($tableId, ['schema' => $schema]); //For Create New Table


        $loadJobConfig = $table->load(
            fopen($filePath, "r")
        )->schema($schema)->skipLeadingRows(1);

        $loadJobConfig->allowQuotedNewlines(true);

        //$data = fgetcsv($open,1000,',');
        //$allData = $loadJobConfig->data($data);
      
        //dd($loadJobConfig);

        $job = $table->runJob($loadJobConfig);

        $backoff = new ExponentialBackoff(10);
        $backoff->execute(function () use ($job) {
            printf('Waiting for job to complete' . PHP_EOL);
            $job->reload();
            if (!$job->isComplete()) {
                throw new Exception('Job has not yet completed', 500);
            }
        });
        // check if the job has errors
        if (isset($job->info()['status']['errorResult'])) {
            $error = $job->info()['status']['errorResult']['message'];
            printf('Error running job: %s' . PHP_EOL, $error);
        } else {
            print('Data imported successfully' . PHP_EOL);
        }

        die();

        //printf('Created table %s' . PHP_EOL, $tableId);
        //die();

        
    }*/
    /*public function competitorInsert(Request $request)
    {
        $file = $request->file('competitorMapping');
        $filePath = $file->getRealPath();

        $open = fopen($filePath, "r");
        $competitorData = [];
        $header = fgetcsv($open,1000,',');
        while (($data = fgetcsv($open, 1000, ","))) {
            $competitorData[] = array_combine($header, $data);
        }

        foreach($competitorData as $key => $value){
            if(isset($value['ref_url']) && !empty($value['ref_url'])){

                $url = parse_url($value['ref_url']);

                if(isset($url['host']) && !empty($url['host'])){
                    $competitorData[$key]['ref_url'] = str_replace('www.', '', $url['host']);
                }else{
                    $competitorData[$key]['ref_url'] = str_replace('www.', '', substr($url['path'], 0, strpos($url['path'], "/")));
                    //substr give www.domain.com remove if there is sub domain after '/'.
                    //str_replace remove 'www.' from domain.
                }
            }
            
        }

        foreach(array_chunk($competitorData,5000) as $data){
            $dataInsert = CompetitorMapping::insert($competitorData);
        }
       
        dd($dataInsert); 
    }*/
    public function importCompetitorToDb()
    {
        
        $path = base_path('Modules/ImportReport/Resources/views/pending_files_competitor/*.csv');

        $files = glob($path);
        
        foreach($files as $file){
           ProcessCompetitorUpload::dispatch($file);
        }
    }
    public function importPrisyncToDb()
    {
        
        $path = base_path('Modules/ImportReport/Resources/views/pending_files_prisync/*.csv');

        $files = glob($path);
        
        foreach($files as $file){
            
            ProcessPrisyncUpload::dispatch($file);

        }
    }
}
