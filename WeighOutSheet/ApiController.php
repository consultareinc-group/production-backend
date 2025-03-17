<?php

/**
 * 
 * replace the SystemName based on the Folder
 * 
*/
namespace App\Http\Controllers\ProductionManagementSystem\WeighOutSheet;

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
        "production_id",
        "sop_reference",
        "date_and_time",
        "material_details",
        "search_keyword",
        "offset"
    ];

    /**
     * 
     * modify required fields based on accepted parameters
     * 
     * */
    protected $required_fields = [
        "production_id",
        "sop_reference",
        "material_details",
       
    ];

    /**
     * 
     * modify response column
     * 
     * */
    protected $response_column = [
        "id",
        "production_id",
        "date_and_time",
        "sop_reference",
        "sop_reference_generated_filename",
        "material_details",
        "activity_logs",
        "status",
        "is_archived",
        "material_code",
        "component_description"
    ];

    /**
     * 
     * modify table name
     * 
     * */
    protected $table = 'weigh_out';
    protected $table_material_details = 'weigh_out_material_details';
    protected $table_activity_logs = 'weigh_out_activity_logs';
    protected $table_user_info = 'user_information';
    protected $table_supplier = 'supplier';
    protected $table_supplier_material = 'supplier_material';
    protected $table_production = 'production_planning';





    public function get(Request $request, $id = null){

        try{

            $query_result = [];

            //This section is intended for fetching specific course record
            if ($id) {
                if($id == 0 ){
                    return $this->response->errorResponse("ID value cannot be zero!");
                }
                else{
                    $query_result = $this->db->table($this->table . ' as wo')
                                             ->select(
                                                'wo.id',
                                                'wo.production_id',
                                                'pp.batch_number',
                                                'wo.date_and_time',
                                                'wo.sop_reference',
                                                'wo.sop_reference_generated_filename',
                                                'wo.date_and_time',
                                                'wo.status',
                                                'wo.is_archived'
                                                )
                                             ->leftJoin($this->table_production . ' as pp', 'wo.production_id', '=', 'pp.id')
                                             ->where('wo.id', $id)
                                             ->first();

                    if(!$query_result){
                        return $this->response->errorResponse(" No Data Based on ID");
                    }

                    $query_result->material_details = $this->db->table($this->table_material_details)->where('weigh_out_id', $query_result->id)->get();

                    $query_result->activity_logs = $this->db->table($this->table_activity_logs)
                                                  ->where('weigh_out_id', $id)
                                                  ->get();
 
               
                        if($query_result->activity_logs->isNotEmpty()){
                            // Step 1: Extract unique user IDs
                            $user_ids = collect($query_result->activity_logs)->pluck('personnel_id')->unique()->toArray();

                            // Step 2: Fetch user account information
                            $account_result = $this->account->table($this->table_user_info)
                                                            ->whereIn('id', $user_ids)
                                                            ->get();

                            // Step 3: Map user data (if available)
                            if ($account_result->isNotEmpty()) {
                                // Create a lookup table for user names
                                $userMap = $account_result->mapWithKeys(function ($user) {
                                    $full_name = trim("{$user->first_name} {$user->middle_name} {$user->last_name} {$user->suffix_name}");
                                    return [$user->id => $full_name];
                                });

                                // Step 4: Assign names to activity logs
                                foreach ($query_result->activity_logs as &$log) {
                                    $log->personnel_name = $userMap[$log->personnel_id] ?? null; // Default if user not found
                                }
                            }
                        }
                    return $this->response->buildApiResponse($query_result, $this->response_column);

                }
            }

            // This section is intended for pagination
            if ($request->has('offset')) {
                $offset = $request->query('offset', ''); // 
                $limit = $request->query('limit', 1000);

                $query_result = $this->db->table($this->table . " as wo")
                                         ->leftJoin($this->table_production . " as pp", "wo.production_id", "=", "pp.id")
                                         ->leftJoin($this->table_material_details . " as md", "wo.id", "=", "md.weigh_out_id")
                                         ->leftJoin($this->table_supplier_material . " as sm", "sm.id", "=", "md.material_id")
                                         ->select(
                                            'wo.id',
                                            'pp.batch_number',
                                            $this->db->raw("GROUP_CONCAT(sm.material_id ORDER BY sm.id SEPARATOR ', ') as material_code"),
                                            $this->db->raw("GROUP_CONCAT(md.description ORDER BY md.id SEPARATOR ', ') as component_description"),
                                            $this->db->raw("GROUP_CONCAT(md.quantity_required ORDER BY md.id SEPARATOR ', ') as quantity_required"),
                                            $this->db->raw("GROUP_CONCAT(md.quantity_weighed ORDER BY md.id SEPARATOR ', ') as quantity_weighed"),
                                            $this->db->raw("GROUP_CONCAT(md.tolerance ORDER BY md.id SEPARATOR ', ') as tolerance"),
                                            'wo.status',
                                            'wo.is_archived'
                                         )
                                        
                                         ->offset($offset)
                                         ->limit($limit) 
                                         ->get();



                return $this->response->buildApiResponse($query_result, $this->response_column);
                
               
                
            }

            // This section is intended for table search
            if ($request->has('search_keyword')) {

                $search_keyword = $request->query('search_keyword', ''); // Default to an empty string if no keyword is provided

                
                $query_result = $this->db->table($this->table . " as wo")
                                         ->leftJoin($this->table_production . " as pp", "wo.production_id", "=", "pp.id")
                                         ->leftJoin($this->table_material_details . " as md", "wo.id", "=", "md.weigh_out_id")
                                         ->leftJoin($this->table_supplier_material . " as sm", "sm.id", "=", "md.material_id")
                                         ->select(
                                            'wo.id',
                                            'pp.batch_number',
                                            $this->db->raw("GROUP_CONCAT(sm.material_id ORDER BY sm.id SEPARATOR ', ') as material_code"),
                                            $this->db->raw("GROUP_CONCAT(md.description ORDER BY md.id SEPARATOR ', ') as component_description"),
                                            $this->db->raw("GROUP_CONCAT(md.quantity_required ORDER BY md.id SEPARATOR ', ') as quantity_required"),
                                            $this->db->raw("GROUP_CONCAT(md.quantity_weighed ORDER BY md.id SEPARATOR ', ') as quantity_weighed"),
                                            $this->db->raw("GROUP_CONCAT(md.tolerance ORDER BY md.id SEPARATOR ', ') as tolerance"),
                                            'wo.status',
                                            'wo.is_archived'
                                         )
                                         ->havingRaw("
                                            pp.batch_number LIKE ? 
                                            OR material_code LIKE ? 
                                        ", ["%$search%", "%$search%"]) // Search keyword applied
                                         ->get();



                return $this->response->buildApiResponse($query_result, $this->response_column);
               
            }
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

            $payload['sop_reference_generated_filename'] = $this->file->generateUniqueFilename($this, $payload['sop_reference']->getClientOriginalName());

            $file_to_upload[] = [
                'file' => $payload['sop_reference'],
                'generated_filename' => $payload['sop_reference_generated_filename']
            ];

            $weigh_out_data = [
                'production_id' => $payload['production_id'],
                'date_and_time' => $payload['date_and_time'],
                'sop_reference' => $payload['sop_reference']->getClientOriginalName(),
                'sop_reference_generated_filename' => $payload['sop_reference_generated_filename'],
                'status' => 0,
                'is_archived' => 0
            ];

            $payload['id'] = $this->db->table($this->table)->insertGetId($weigh_out_data);
            $payload['sop_reference'] = $payload['sop_reference']->getClientOriginalName();

            if(!$payload['id']){
                $this->db->rollback();
                return $this->rseponse->errorResponse("Can't Save Data");
            }
            else{
                $activity_log = [
                    "action" => 0,
                    "weigh_out_id" => $payload['id'],
                    "date_and_time" => now(),
                    "personnel_id" => $this->user_info->getuserId(),
                ];

                $activity_log['id'] = $this->db->table($this->table_activity_logs)->insertGetId($activity_log);
                if(!$activity_log['id']){
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save Activity Logs");
                }


                foreach($payload['material_details'] as &$material_data){
                    $material_data['weigh_out_id'] = $payload['id'];                  
                }

                if(!$this->db->table($this->table_material_details)->insert($payload['material_details'])){
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save Material Details");
                }

                foreach($file_to_upload as $ftu){
                    if(!$this->file->saveFile($ftu['file'], $ftu['generated_filename'], $this)){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Upload File");
                    }

                }
                $payload['activity_logs'] = $activity_log;
                
                $this->db->commit();
                return $this->response->buildApiResponse($payload, $this->response_column);

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
                "weigh_out_id" => $id,
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
        try{
            $this->db->beginTransaction();

            $payload['sop_reference_generated_filename'] = $this->file->generateUniqueFilename($this, $payload['sop_reference']->getClientOriginalName());

            $query_result = $this->db->table($this->table)->where("id", $payload['id'])->first();

            if(!$query_result){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Find Data using Id");
            }
            $file_to_delete[] = [
                'file' => $query_result->sop_reference_generated_filename
            ];

            $file_to_upload[] = [
                'file' => $payload['sop_reference'],
                'generated_filename' => $payload['sop_reference_generated_filename']
            ];

            $weigh_out_data = [
                'id' => $payload['id'],
                'production_id' => $payload['production_id'],
                'date_and_time' => $payload['date_and_time'],
                'sop_reference' => $payload['sop_reference']->getClientOriginalName(),
                'sop_reference_generated_filename' => $payload['sop_reference_generated_filename'],
                'status' => isset($payload['status'])? $payload['status'] : $query_result->status,
                'is_archived' => isset($payload['is_archived'])? $payload['is_archived'] : $query_result->is_archived
            ];

            if(!$this->db->table($this->table)->where('id', $payload['id'])->update($weigh_out_data)){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update Data");
            }

            $payload['sop_reference'] = $payload['sop_reference']->getClientOriginalName();

            if(!$payload['id']){
                $this->db->rollback();
                return $this->rseponse->errorResponse("Can't Save Data");
            }
            else{


                if(!$this->db->table($this->table_material_details)->where("weigh_out_id", $payload['id'])->delete()){
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Delete Material Details");
                }
                $activity_log = [];

                if($weigh_out_data['status'] != $query_result->status){
                    $activity_log = [
                        "action" => $payload['status'],
                        "weigh_out_id" => $payload['id'],
                        "date_and_time" => now(),
                        "personnel_id" => $this->user_info->getuserId(),
                    ];
    
                    $activity_log['id'] = $this->db->table($this->table_activity_logs)->insertGetId($activity_log);
                    if(!$activity_log['id']){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Save Activity Logs");
                    }
                }                
                
                foreach($payload['material_details'] as &$material_data){
                    $material_data['weigh_out_id'] = $payload['id'];                    
                }

                if(!$this->db->table($this->table_material_details)->insert($payload['material_details'])){
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save Material Details");
                }

                foreach($file_to_delete as $ftd){
                    if(!$this->file->deleteFile($ftd['file'], $this)){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Delete File");
                    }
                }

                foreach($file_to_upload as $ftu){
                    if(!$this->file->saveFile($ftu['file'], $ftu['generated_filename'], $this)){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Upload File");
                    }

                }
                $payload['activity_logs'] = $activity_log;
                
                $this->db->commit();
                return $this->response->buildApiResponse($payload, $this->response_column);




            }





        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }
    }

    public function delete($id){

        //check if the id is numeric and has value
        if (empty($id) && !is_numeric($id)) {
            return $this->response->errorResponse("Invalid Request");
        }

        $payload = $payload->all();
        if(!isset($payload['id']) || empty($payload['id']) || !is_numeric($payload['id'])){
            //if id is not set in $request, empty or non numeric
            return $this->response->invalidParameterResponse();
        }
        if($payload['id'] != $id){
            //if ids doesnt match
            return $this->response->errorResponse("ID doesn't match!");
        }

        try{

             /**
             * 
             * 
             * insert your code here
             * 
             * can remove this comment after
             * 
             * 
             * */

        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }
    }

    public function upload(Request $request, $id){

         /**
         * 
         * start with other validations here
         * 
         * */


        try{

             /**
             * 
             * 
             * insert your code here
             * 
             * can remove this comment after
             * 
             * 
             * */

        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }
    }
}