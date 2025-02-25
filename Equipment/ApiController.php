<?php

/**
 * 
 * replace the SystemName based on the Folder
 * 
*/
namespace App\Http\Controllers\ProductionManagementSystem\Equipment;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Helpers\ResponseHelper;
use App\Helpers\ValidationHelper;
use App\Helpers\UserInfoHelper;
use App\Helpers\FileHelper;
use DateTime;


class ApiController extends Controller
{
    public const GET_PERMISSION_ALIAS = null;

    public const POST_PERMISSION_ALIAS = null;

    public const PUT_PERMISSION_ALIAS = null;

    public const DELETE_PERMISSION_ALIAS = null;

    public const FILE_UPLOAD_PERMISSION_ALIAS = null;



    protected $response;
    protected $validation;
    protected $db;
    protected $file;
    protected $user_info;
    protected $account;
    public function __construct(Request $request)
    {
        $this->file = new FileHelper();
        $this->response = new ResponseHelper($request);
        $this->validation = new ValidationHelper($request);
        $this->user_info = new UserInfoHelper();
        /**
         * 
         *  Rename system_database_connection based on preferred database on database.php
         * 
        */
        $this->db = DB::connection("production_database_connection");
        $this->account = DB::connection("accounts_connection");
    }

     /**
     * 
     * modify accepted parameters
     * 
     * */
    protected $accepted_parameters = [
        "id",
        "equipment_name",
        "category_id",
        "manufacturer",
        "model_number",
        "serial_number",
        "purchase_date",
        "purchase_price",
        "supplier",
        "warranty_expiration",
        "condition",
        "location",
        "assigned_to",
        "maintenance_schedule",
        "status",
        "description",
        "equipment_image",
        "safety_manual",
        "operation_manual",
        "maintenance_manual",
        "search_keyword",
        "activity_logs",
        "is_archived"
    ];

    /**
     * 
     * modify required fields based on accepted parameters
     * 
     * */
    protected $required_fields = [
       "equipment_name",
       "category_id",
       "manufacturer",
       "model_number",
       "serial_number",
       "purchase_date",
       "location",
       "assigned_to",
       "description"
    ];

    /**
     * 
     * modify response column
     * 
     * */
    protected $response_column = [
       "id",
        "equipment_name",
        "equipment_number",
        "category_id",
        "manufacturer",
        "model_number",
        "serial_number",
        "purchase_date",
        "purchase_price",
        "supplier",
        "warranty_expiration",
        "condition",
        "location",
        "assigned_to",
        "maintenance_schedule",
        "status",
        "description",
        "equipment_image",
        "equipment_image_generated_filename",
        "safety_manual",
        "safety_manual_generated_filename",
        "operation_manual",
        "operation_manual_generated_filename",
        "maintenance_manual",
        "maintenance_manual_generated_filename",
        "activity_logs",
        "is_archived",
    ];

    /**
     * 
     * modify table name
     * 
     * */
    protected $table = 'equipments';
    protected $table_category = 'category_lists';
    protected $table_activity_logs = 'equipment_activity_logs';
    protected $table_user_info = 'user_information';



    public function get(Request $request, $id = null){

        try{
            if($id){
                if($id == 0){
                    return $this->response->errorResponse("ID must not be zero");
                }
                $query_result = $this->db->table($this->table)
                                         ->leftJoin($this->table_category, $this->table_category . '.id', '=', $this->table . '.category_id')
                                         ->where($this->table .'.id', $id)
                                         ->first();
                $query_result->activity_logs = $this->db->table($this->table_activity_logs)
                                                                  ->where('equipment_id', $id)
                                                                  ->get();

                $personnel_ids = $query_result->activity_logs->pluck('user_id')
                                                                       ->unique()
                                                                       ->toArray();
                                                              
                $personnel_names = [];

                if($personnel_ids){
                    $personnel_names = $this->account->table($this->table_user_info)
                                                     ->whereIn('id', $personnel_ids)
                                                     ->selectRaw("id, CONCAT(
                                                        first_name, 
                                                        IF(middle_name IS NOT NULL AND middle_name != '', CONCAT(' ', middle_name), ''), 
                                                        ' ', last_name, 
                                                        IF(suffix_name IS NOT NULL AND suffix_name != '', CONCAT(' ', suffix_name), '')
                                                     ) AS full_name")
                                                     ->pluck('full_name', 'id'); // Get full names indexed by personnel_id


                    foreach($query_result->activity_logs as &$logs){
                        $logs->personnel_name = $personnel_names[$logs->user_id] ?? 'Unknown';
                    }

                }

            }
            if($request->has('offset')){
                $query_result = $this->db->table($this->table . ' as e')
                                         ->leftJoin($this->table_category . ' as c', 'c.id', '=', 'e.category_id')
                                         ->select(
                                            'e.id',
                                            'e.equipment_number',
                                            'e.equipment_name',
                                            'c.category_name',
                                            'e.location',
                                            'e.status',
                                            'e.is_archived'
                                         )
                                         ->where('e.is_archived', 0)
                                         ->offset((int) $request->query('offset', 0)) // Ensure it's an integer with a default of 0
                                         ->limit(1000)
                                         ->get();


            }
            if($request->has('search_keyword')){
                $search_keyword = trim($request->query('search_keyword', ''), '"');

                $query_result = $this->db->table($this->table . ' as e')
                                         ->leftJoin($this->table_category . ' as c', 'c.id', '=', 'e.category_id')
                                         ->select(
                                            'e.id',
                                            'e.equipment_number',
                                            'e.equipment_name',
                                            'c.category_name',
                                            'e.location',
                                            'e.status',
                                            'e.is_archived'
                                         )
                                         ->where('e.is_archived', 0)
                                         ->when($search_keyword, function ($query, $search_keyword) {
                                            return $query->where(function ($subQuery) use ($search_keyword) {
                                                $subQuery->where('e.equipment_number', 'LIKE', "%$search_keyword%")
                                                        ->orWhere('e.equipment_name', 'LIKE', "%$search_keyword%")
                                                        ->orWhere('c.category_name', 'LIKE', "%$search_keyword%")
                                                        ->orWhere('e.location', 'LIKE', "%$search_keyword%");
                                            });
                                         })
                                         ->get();


            }


            if(empty($query_result)){
                $query_result = [];
            }
        
            return $this->response->buildApiResponse($query_result,$this->response_column);

        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }
            
            
       
    }

    public function post(Request $request){
        
        $payload = $this->validation->validateRequest($request, $this->accepted_parameters, $this->required_fields);

        //check if the $payload has error validation key 
        if(isset($payload['error_validation'])){
            $this->db->rollback();
            return $this->response->errorResponse($payload['message']);
        }
        try{
            $this->db->beginTransaction();

            $eq_d = $this->db->table($this->table)->orderBy('id','desc')->first();

            $equipment_number = $eq_d->equipment_number ?? 'EQP-0';

            // Ensure we extract both parts correctly
            [$prefix, $num] = explode('-', $equipment_number) + ['EQP', 0];
            
            // Convert number to an integer and increment it
            $number = max(0, (int) $num) + 1;
            
            // Reconstruct the equipment number
            $equipment_number = "{$prefix}-{$number}";
            


            $file_to_upload = [];
            $generated_filenames = [];
            $file_keys = ['equipment_image', 'safety_manual', 'operation_manual', 'maintenance_manual'];

            foreach ($file_keys as $key) {
                $generated_filenames[$key] = $this->file->generateUniqueFilename($this, $payload[$key]);

                $file_to_upload[] = [
                    "file" => $payload[$key],
                    "generated_filename" => $generated_filenames[$key]
                ];
            }

            $equipment_data = [
                "equipment_number" => $equipment_number,
                "equipment_name" => $payload['equipment_name'],
                "category_id" => $payload['category_id'],
                "manufacturer" => $payload['manufacturer'],
                "model_number" => $payload['model_number'],
                "serial_number" => $payload['serial_number'],
                "purchase_date" => $payload['purchase_date'],
                "purchase_price" => (isset($payload['purchase_price']))?$payload['purchase_price'] : null,
                "supplier" => (isset($payload['supplier']))? $payload['supplier'] :null ,
                "warranty_expiration" => (isset($payload['warranty_expiration'])) ?$payload['warranty_expiration'] : null,
                "condition" => $payload['condition'],
                "location" => $payload['location'],
                "assigned_to" => (isset($payload['assigned_to']))?$payload['assigned_to']:null,
                "maintenance_schedule" => (isset($payload['maintenance_schedule']))?$payload['maintenance_schedule']:null,
                "status" => 1,
                "description" => $payload['description'],
                "is_archived" => 0
            ];

            // Dynamically add file details to equipment_data
            foreach ($file_keys as $key) {
                $equipment_data[$key] = $payload[$key]->getClientOriginalName();
                $equipment_data[$key . "_generated_filename"] = $generated_filenames[$key];
            }

            $equipment_data['id'] = $this->db->table($this->table)->insertGetId($equipment_data);

            if(!$equipment_data['id']){
                $this->db->rollback();
                return $this->response->errorResponse("Cant Save Data!");
            }
        
            else{

                $activity_logs = [
                    "equipment_id" => $equipment_data['id'],
                    "action" => $equipment_data['status'],
                    "user_id" => $this->user_info->getUserId(),
                    "date_time" => now()
                ];

                $activity_logs['id'] = $this->db->table($this->table_activity_logs)->insertGetId($activity_logs);

                if(empty($activity_logs['id'])){
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save Data");
                }

                $activity_logs['personnel'] = trim(
                    $this->user_info->getUserInformation()['first_name'] . ' ' .
                    ($this->user_info->getUserInformation()['middle_name'] ?? '') . ' ' .
                    $this->user_info->getUserInformation()['last_name'] . ' ' .
                    ($this->user_info->getUserInformation()['suffix_name'] ?? '')
                );
                

                $equipment_data['activity_logs'] = $activity_logs;
                

                foreach($file_to_upload as $files){
                    if(!$this->file->saveFile($files['file'], $files['generated_filename'], $this)){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Save File");
                    }
                }

                $this->db->commit();
                return $this->response->buildApiResponse($equipment_data, $this->response_column);

            }

            

        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }

    }

    public function put(Request $request, $id){

        $for_archived = ['is_archived', 'id'];
        $for_status = ['status', 'id'];

        $request_keys = array_keys($request->all());
        sort($request_keys); 
        sort($for_archived);
        sort($for_status);

        $edit_request = $request->all();

        if ($request_keys === $for_status) {

            if($id == 0){
                return $this->response->errorResponse("Id cannot be zero");
            }
    
            if($edit_request['id'] != $id){
                return $this->response->errorResponse("Ids Does not match");
            }
    
           
            $this->db->beginTransaction();

            if($this->db->table($this->table)->where('id', $id)->where($edit_request)->exists()){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update. Data have Similar Status");
            }   

            if(!$this->db->table($this->table)->where("id",$id)->update($edit_request)){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update Data");
            }  


            $activity_logs = [
                "equipment_id" => $id,
                "action" => $edit_request['status'],
                "user_id" => $this->user_info->getUserId(),
                "date_time" => now()
            ];

            $activity_logs['id'] = $this->db->table($this->table_activity_logs)->insertGetId($activity_logs);

            if(empty($activity_logs['id'])){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Save Data");
            }

            $activity_logs['personnel'] = trim(
                $this->user_info->getUserInformation()['first_name'] . ' ' .
                ($this->user_info->getUserInformation()['middle_name'] ?? '') . ' ' .
                $this->user_info->getUserInformation()['last_name'] . ' ' .
                ($this->user_info->getUserInformation()['suffix_name'] ?? '')
            );
            

            $edit_request['activity_logs'] = $activity_logs;

            $this->db->commit();
            return $this->response->buildApiResponse($edit_request, $this->response_column);

        }

        if ($request_keys === $for_archived) {
            
            if($id == 0){
                return $this->response->errorResponse("Id cannot be zero");
            }

            if($edit_request['id'] != $id){
                return $this->response->errorResponse("Ids Does not match");
            }


            $this->db->beginTransaction();

            if($this->db->table($this->table)->where('id', $id)->where($edit_request)->exists()){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update. Data have Similar Status");
            }   

            if(!$this->db->table($this->table)->where("id",$id)->update($edit_request)){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update Data");
            }  

            $this->db->commit();
            return $this->response->successResponse("Data has been archived");

        }

        
        

        $payload = $this->validation->validateRequest($request, $this->accepted_parameters, $this->required_fields);
        //check if the $payload has error validation key 
        if(isset($payload['error_validation'])){
            $this->db->rollback();
            return $this->response->errorResponse($payload['message']);
        }

        if($id !== $payload['id']){
            return $this->response->errorResponse("Ids doesn't Match");
        }
        if($id === 0){
            return $this->response->errorResponse("Id must not zero");
        }

        try{

            $this->db->beginTransaction();

            $query_result = $this->db->table($this->table)->where('id', $id)->first();
            if(empty($query_result)){
                return $this->response->errorResponse("No Data with given");
            }

            $file_to_upload = [];

            $files_to_delete = [];

            $generated_filenames = [];
            
            $file_keys = ['equipment_image', 'safety_manual', 'operation_manual', 'maintenance_manual'];

            foreach ($file_keys as $key) {
                $generated_filenames[$key] = $this->file->generateUniqueFilename($this, $payload[$key]);

                $gen_fn = $key . "_generated_filename";
                $files_to_delete[] = [
                    'file' => $query_result->$gen_fn 
                ];

                $file_to_upload[] = [
                    "file" => $payload[$key],
                    "generated_filename" => $generated_filenames[$key]
                ];
            }

            $equipment_data = [
                "id" => $id,
                "equipment_number" => $query_result->equipment_number,
                "equipment_name" => $payload['equipment_name'],
                "category_id" => $payload['category_id'],
                "manufacturer" => $payload['manufacturer'],
                "model_number" => $payload['model_number'],
                "serial_number" => $payload['serial_number'],
                "purchase_date" => $payload['purchase_date'],
                "purchase_price" => (isset($payload['purchase_price']))?$payload['purchase_price'] : null,
                "supplier" => (isset($payload['supplier']))? $payload['supplier'] :null ,
                "warranty_expiration" => (isset($payload['warranty_expiration'])) ?$payload['warranty_expiration'] : null,
                "condition" => $payload['condition'],
                "location" => $payload['location'],
                "assigned_to" => (isset($payload['assigned_to']))?$payload['assigned_to']:null,
                "maintenance_schedule" => (isset($payload['maintenance_schedule']))?$payload['maintenance_schedule']:null,
                "status" => $query_result->status,
                "description" => $payload['description'],
                "is_archived" => $query_result->is_archived
            ];

            // Dynamically add file details to equipment_data
            foreach ($file_keys as $key) {
                $equipment_data[$key] = $payload[$key]->getClientOriginalName();
                $equipment_data[$key . "_generated_filename"] = $generated_filenames[$key];
            }


            if(!$this->db->table($this->table)->where('id',$id)->update($equipment_data)){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update Data");
            }

            foreach($files_to_delete as $ftd){
                if(!$this->file->deleteFile($ftd['file'], $this)){
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Update Data. Can't Delete former file");
                }
            }
            foreach($file_to_upload as $files){
                if(!$this->file->saveFile($files['file'], $files['generated_filename'], $this)){
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save File");
                }
            }

            

            $this->db->commit();
            return $this->response->buildApiResponse($equipment_data, $this->response_column);

        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }
    }

    public function delete($id){

    }

    public function upload(Request $request, $id){

    }
}