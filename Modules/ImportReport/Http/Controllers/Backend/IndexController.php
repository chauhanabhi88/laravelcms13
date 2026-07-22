<?php

namespace Modules\ImportReport\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\ImportReport\Entities\ImportReport;
use Modules\ImportReport\Repositories\ImportReportRepository;
use Modules\ImportReport\Http\Requests\CreateRequest;
use Modules\ImportReport\Http\Requests\UpdateRequest;
use Modules\ImportReport\Http\Requests\ImportCompetitorMappingRequest;
use Modules\ImportReport\Http\Requests\PrisyncVerticalReportRequest;
use Maatwebsite\Excel\Facades\Excel;
use Modules\ImportReport\Imports\CompetitorMappingImport;
use Modules\ImportReport\Imports\PrisyncVerticalReportImport;
use Modules\ImportReport\Export\ReportExport;
use File;

use Modules\Core\Http\Controllers\BackendController;



class IndexController extends BackendController
{
    /**
     * @var ImportReportRepository
     */
    private $importreport;

    /**
      * @var UserEntity
     */
    private $importreportEntity;

    public function __construct(ImportReportRepository $importreportRepo, ImportReport $importreport)
    {
        parent::__construct();

        $this->importreport = $importreportRepo;
        $this->importreportEntity = $importreport;
    }
    /**
     * Display a listing of the resource.
      * @return Response
     */
    public function index(Request $request)
    {
        try
        {
            //$uploadLimit = settings('importreport', 'max_upload_size');
            //dd($uploadLimit);
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("importreport.cache.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
           // $maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
            //$columns = $this->importreport->sortColumns();
            //$collection = $this->importreport->pagination($request);
            //$filters = $this->importreport->getFilters($request);
            // $statusOptions = $this->importreport->getStatusOptions(true);
            
            return view('importreport::backend.index', compact('request'));
        }
        catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with("error", $e->getMessage());
        }
    }
    public function importCompetitorMapping(Request $request){
        try{
            //$data = $this->importreport->competitorImport($request);

            //$data = $this->importreport->competitorInsert($request);

            $request->validate([
                'competitorMapping' => 'required|mimes:csv'
            ]);
            //$filePath = file_get_contents($request->competitorMapping->getRealPath());
            //$fileData = preg_split("/\r\n|\n|\r/", $filePath);
            //dd(count($fileData));
            $file = $request->file('competitorMapping');
            $filePath = $file->getRealPath();
            $open = fopen($filePath, "r");
            $competitorData = array();
            while (($data = fgetcsv($open)) !==FALSE ) {
                $competitorData[] = $data;
            }
            fclose($open);

            
            
            $data = array_slice($competitorData , 1);
            
            $parts = (array_chunk($data,10000));
            
            //dd($result);
            foreach($parts as $index => $part){
                $fileName = base_path('Modules/ImportReport/Resources/views/pending_files_competitor/'.date('y-m-d-H-i-s').$index.'.csv');
                $fd = fopen ($fileName, "a");
                foreach($part as $value){
                    fputcsv($fd, $value);
                }
                fclose($fd);
                /*$result = [];
                array_walk_recursive($part, function($item) use (&$result) {
                    $result[] = $item;
                });
                dd($result);*/
                
                //dd($part);
                //file_put_contents($fileName,$part);
            }
            //die();
            //dd($parts);
            $import = $this->importreport->importCompetitorToDb();

            //return view('importreport::backend.index');
            //Excel::import(new CompetitorMappingImport, request()->file('competitorMapping'),\Maatwebsite\Excel\Excel::CSV);
            

            return response()->json([
                'type' => 'success',
                //'message' => trans("importreport::importreport.messages.import_complete_for_competitor"),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
    

    public function prisyncVerticalReport(Request $request){
        try{

            $request->validate([
                'prisyncverticalreport' => 'required|mimes:csv'
            ]);
            $file = file($request->prisyncverticalreport->getRealPath());
            $data = array_slice($file , 1);
            
            $parts = (array_chunk($data,10000));

            

            foreach($parts as $index => $part){
                $fileName = base_path('Modules/ImportReport/Resources/views/pending_files_prisync/'.date('y-m-d-H-i-s').$index.'.csv');

                file_put_contents($fileName, $part);
            }

            $import = $this->importreport->importPrisyncToDb();

            //Excel::import(new PrisyncVerticalReportImport, request()->file('prisyncverticalreport'),\Maatwebsite\Excel\Excel::CSV);
            
            return response()->json([
                'type' => 'success',
                //'message' => trans("importreport::importreport.messages.import_complete_for_prisync"),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function filters(Request $request)
    {
        
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("importreport.cache.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("importreport.cache.name"), $request);
            $columns = $this->importreport->sortColumns();
            $filters = $this->importreport->getFilters($request);
            $collection = $this->importreport->pagination($request);
            $statusOptions = $this->importreport->getStatusOptions(true);
            
            $content = view('importreport::backend.partials.grid', compact('request', 'collection', 'columns', 'filters','statusOptions'));
            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString()
                ],
                'message' => $request->get('message')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function export(Request $request){
        try {
            $data = $this->importreport->export($request);
            
        $columnNames = ['product_name', 'product_code', 'brand', 'price','site'/*,'ref_url'*/];
            if (empty($data)) {
                return $this->importreport->exportCsv($columnNames, [], 'Report');
            } else {
                return $this->importreport->exportCsv($columnNames, $data, 'Report');
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.importreport.index', updateUrlParams())->with("error", $e->getMessage() . $e->getTraceAsString());
        }
    }
}
