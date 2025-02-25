<?php

/**
 * 
 * replace the SystemName based on the Folder
 * 
*/
namespace App\Http\Controllers\ProductionManagementSystem\Processing;

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
        "processing_processes",
        "search_keyword",
        "status",
        "is_archived"
    ];

    /**
     * 
     * modify required fields based on accepted parameters
     * 
     * */
    protected $required_fields = [
       "production_id",
       "sop_reference"
    ];

    /**
     * 
     * modify response column
     * 
     * */
    protected $response_column = [
       "id",
        "production_id",
        "sop_reference",
        "sop_reference_generated_filename",
        "processing_processes",
        "search_keyword",
        "status",
        "is_archived",
        "production",
        "batch_number",
        "process_name",
        "operator_names",
        "date_and_time_of_processing"
    ];

    /**
     * 
     * modify table name
     * 
     * */
    protected $table = 'processing';
    protected $table_processes = 'processing_processes';
    protected $table_activity_logs = 'processing_activity_logs';
    protected $table_equipments = 'processing_equipment_details';
    protected $table_user_info = 'user_information';
    protected $table_production = 'production_planning';


    public function get(Request $request, $id = null){

        try{
            
            if($id){
                if($id == 0){
                    return $this->response->errorResponse("Id value can't be zero");
                }
                else{

                    $query_result = $this->db->table($this->table)->where('id',$id)->first();

                    if(!$query_result){
                        return $this->response->errorResponse("No Data Found");
                    }
                    else{

                        $query_result->activity_logs = $this->db->table($this->table_activity_logs)
                                                  ->where('processing_id', $id)
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
                        

                        $query_result->processing_processes = $this->db->table($this->table_processes)->where('processing_id', $id)->get();

                        if(!$query_result->processing_processes){
                            return $this->response->buildApiResponse($query_result, $this->response_column);
                        }
                        else{

                            $ids = collect($query_result->processing_processes)->pluck('id')->toArray();

                            $equipment_result = $this->db->table($this->table_equipments)->whereIn('processing_process_id', $ids)->get();
                            if ($equipment_result->isEmpty()) {
                                return $this->response->buildApiResponse($query_result, $this->response_column);
                            }

                            //Create a lookup table (hashmap) for materials by processing_process_id
                            $materialMap = [];
                            foreach ($equipment_result as $material) {
                                $materialMap[$material->processing_process_id][] = $material;
                            }

                            //Assign materials efficiently using the hashmap
                            foreach ($query_result->processing_processes as &$pp) {
                                $pp->processing_materials = $materialMap[$pp->id] ?? []; // Assign or default to empty array
                            }

                            return $this->response->buildApiResponse($query_result, $this->response_column);

                        }

                    }

                }

            }
            elseif ($request->has('offset')) {
                $offset = (int) $request->input('offset'); // Ensure it's an integer
                $limit = (int) ($request->input('limit') ?? 1000); // Default limit if not provided
            
                $query_result = $this->db->table($this->table . " as pro")
                    ->leftJoin($this->table_production . " as pp", "pp.id", "=", "pro.production_id")
                    ->leftJoin($this->table_processes . " as proc", "proc.processing_id", "=", "pro.id") // Correct alias
                    ->select(
                        'pro.id',
                        'pp.id as production_id',
                        'pp.batch_number',
                        $this->db->raw("GROUP_CONCAT(proc.process_name ORDER BY proc.id SEPARATOR ', ') as process_name"),
                        $this->db->raw("GROUP_CONCAT(proc.date_and_time_of_processing ORDER BY proc.id SEPARATOR ', ') as date_and_time_of_processing"),
                        $this->db->raw("GROUP_CONCAT(proc.operator_names ORDER BY proc.id SEPARATOR ', ') as operator_names"),
                        'pro.status',
                        'pro.is_archived'
                    )
                    ->groupBy('pro.id', 'pp.id', 'pp.batch_number', 'pro.status', 'pro.is_archived') // Ensure all non-aggregated columns are grouped
                    ->offset($offset)
                    ->limit($limit)
                    ->get();
            
                return $this->response->buildApiResponse($query_result, $this->response_column);
            }
            
            elseif($request->has('search_keyword')){

                if ($request->has('search_keyword')) {
                    $search = $request->input('search_keyword');
                
                    $query_result = $this->db->table($this->table . " as pro")
                        ->leftJoin($this->table_production . " as pp", "pp.id", "=", "pro.production_id")
                        ->leftJoin($this->table_processes . " as proc", "proc.processing_id", "=", "pro.id")
                        ->select(
                            'pro.id',
                            'pp.id as production_id',
                            'pp.batch_number',
                            $this->db->raw("GROUP_CONCAT(proc.process_name ORDER BY proc.id SEPARATOR ', ') as process_name"),
                            $this->db->raw("GROUP_CONCAT(proc.date_and_time_of_processing ORDER BY proc.id SEPARATOR ', ') as date_and_time_of_processing"),
                            $this->db->raw("GROUP_CONCAT(proc.operator_names ORDER BY proc.id SEPARATOR ', ') as operator_names"),
                            'pro.status',
                            'pro.is_archived'
                        )
                        ->groupBy('pro.id', 'pp.id', 'pp.batch_number', 'pro.status', 'pro.is_archived')
                
                        // **Search Condition**
                        ->havingRaw("
                            pp.batch_number LIKE ? 
                            OR process_name LIKE ? 
                            OR operator_names LIKE ?
                        ", ["%$search%", "%$search%", "%$search%"]) // Search keyword applied
                
                        ->get();
                
                    return $this->response->buildApiResponse($query_result, $this->response_column);
                }
            }
            else{
                return $this->response->errorResponse("Invalid Parameters");
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
            return $this->response->errorResponse($payload['message']);
        }
        try{
            $files_to_upload = [];

            $this->db->beginTransaction();

            $processing_data = [
                'production_id' => $payload['production_id'],
                'sop_reference' => $payload['sop_reference']->getClientOriginalName(),
                'sop_reference_generated_filename' => $this->file->generateUniqueFilename($this, $payload['sop_reference']->getClientOriginalName()),
                'status' => 0,
                'is_archived' => 0
            ];

            $files_to_upload[] = [
                'file' => $payload['sop_reference'],
                'generated_filename' => $processing_data['sop_reference_generated_filename']
            ];

            $payload['sop_reference'] = $payload['sop_reference'] ->getClientOriginalName();
            $payload['sop_reference_generated_filename'] =  $processing_data['sop_reference_generated_filename'];

            $payload['id'] = $this->db->table($this->table)->insertGetId($processing_data);

            if(empty($payload['id'])){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Save Data");
            }
            else{


                if(isset($payload['processing_processes']) || !empty($payload['processing_processes'])){
                        
                    $processing_process_data = [];

                    foreach($payload['processing_processes'] as &$processes){

                        $generated_filename = $this->file->generateUniqueFilename($this, $processes['process_steps_file']->getClientOriginalName());

                        $processing_process_data[] = [
                            'processing_id' => $payload['id'],
                            'process_name' => $processes['process_name'],
                            'process_steps_file' => $processes['process_steps_file']->getClientOriginalName(),
                            'process_steps_generated_filename' => $generated_filename,
                            'date_and_time_of_processing' => $processes['date_and_time_of_processing'],
                            'operator_names' => $processes['operator_names'],
                            'comment_or_notes' => isset($processes['comment_or_notes']) ? $processes['comment_or_notes'] : null
                        ];
                        
                       

                        $files_to_upload[] = [
                            'file' => $processes['process_steps_file'],
                            'generated_filename' => $generated_filename
                        ];

                        $processes['process_steps_generated_filename'] = $generated_filename;
                        $processes['process_steps_file'] = $processes['process_steps_file']->getClientOriginalName();


                    }

                    if(!$this->db->table($this->table_processes)->insert($processing_process_data)){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Save Data");
                    }
                    else{
                        
                        $processes_query_result = $this->db->table($this->table_processes)->where('processing_id', $payload['id'])->get();

                        $equipment_details_data = [];

                        foreach($payload['processing_processes'] as &$pc){
                            foreach($processes_query_result as $pq_result){
                                if($pc['process_steps_generated_filename'] == $pq_result->process_steps_generated_filename){
                                    $pc['id'] = $pq_result->id; 
                                }
                            }

                            foreach($pc['processing_equipment_details'] as &$equipment_details){
                                $equipment_details['processing_process_id'] = $pc['id'];

                                $equipment_details_data[] = [
                                    "processing_process_id" =>  $pc['id'],
                                    "equipment_name" => $equipment_details['equipment_name'],
                                    "verification" => $equipment_details['verification'],
                                    "verification_status" =>$equipment_details['verification_status'],
                                    "date_code_verification" => isset($equipment_details['date_code_verification']) ? $equipment_details['date_code_verification'] : null,
                                    "qc_inspector" => isset($equipment_details['qc_inspector']) ? $equipment_details['qc_inspector'] : null,
                                    "issue_identified" => isset($equipment_details['isse_identified']) ? $equipment_details['issue_identified'] : null,
                                    "corrected_by" => isset($equipment_details['corrected_by']) ? $equipment_details['corrected_by'] : null,
                                    "corrective_action" => isset($equipment_details['corrective_action']) ? $equipment_details['corrective_action'] : null,
                                    "corrected_date_and_time" => isset($equipment_details['corrected_date_and_time']) ? $equipment_details['corrected_date_and_time'] : null
                                ];
                            }
                        }

                        if(!$this->db->table($this->table_equipments)->insert($equipment_details_data)){
                            $this->db->rollback();
                            return $this->response->errorResponse("Can't Save Data");
                        }
                        else{
                            
                            foreach($files_to_upload as $file){
                                if(!$this->file->saveFile($file['file'], $file['generated_filename'], $this)){
                                    $this->db->rollback();
                                    return $this->response->errorResponse("Can't upload File");
                                }
                            }

                            $this->db->commit();
                            return $this->response->buildApiResponse($payload, $this->response_column);
                            
                        }
                    }
                }
                else{

                    $this->db->commit();
                    return $this->response->buildApiResponse($payload,$this->response_column);

                }
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
                "processing_id" => $id,
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
            return $this->response->errorResponse($payload['message']);
        }
        if($id !== $payload['id']){
            return $this->response->errorResponse("Ids doesn't Match");
        }
        if($id === 0){
            return $this->response->errorResponse("Id must not zero");
        }
        try{



            $files_to_upload = [];

            $files_to_delete = [];

            $this->db->beginTransaction();

            $processing_data = [
                'id' => $payload['id'],
                'production_id' => $payload['production_id'],
                'sop_reference' => $payload['sop_reference']->getClientOriginalName(),
                'sop_reference_generated_filename' => $this->file->generateUniqueFilename($this, $payload['sop_reference']->getClientOriginalName()),
            ];

            $files_to_upload[] = [
                'file' => $payload['sop_reference'],
                'generated_filename' => $processing_data['sop_reference_generated_filename']
            ];

            $payload['sop_reference'] = $processing_data['sop_reference'];
            $payload['sop_reference_generated_filename'] = $processing_data['sop_reference_generated_filename'];

            $query_result = $this->db->table($this->table)->where('id', $id)->get()->first();

            if(!$query_result){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Find information with the Id Provided");
            }

            $files_to_delete[] = [
                'file' => $query_result->sop_reference_generated_filename
            ];
            
            if(!$this->db->table($this->table)->where('id', $id)->update($processing_data)){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update Data");
            }
            else{

                if(isset($payload['processing_processes']) || !empty($payload['processing_processes'])){

                    

                    $ids = [];
                    $processing_process_result = $this->db->table($this->table_processes)->where('processing_id', $id)->get();

                    foreach($processing_process_result as $pp_result){
                        $files_to_delete[] = [
                            'file' => $pp_result->process_steps_generated_filename
                        ];
                        $ids[] = $pp_result->id;
                    }

                    if(!$this->db->table($this->table_equipments)->whereIn('processing_process_id', $ids)->delete()){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Delete Processing Equipments");
                    }

                    if(!$this->db->table($this->table_processes)->where('processing_id', $payload['id'])->delete()){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Delete Processing Processes");
                    }
                        
                    $processing_process_data = [];

                    foreach($payload['processing_processes'] as &$processes){

                        $generated_filename = $this->file->generateUniqueFilename($this, $processes['process_steps_file']->getClientOriginalName());

                        $processing_process_data[] = [
                            'processing_id' => $payload['id'],
                            'process_name' => $processes['process_name'],
                            'process_steps_file' => $processes['process_steps_file']->getClientOriginalName(),
                            'process_steps_generated_filename' => $generated_filename,
                            'date_and_time_of_processing' => $processes['date_and_time_of_processing'],
                            'operator_names' => $processes['operator_names'],
                            'comment_or_notes' => isset($processes['comment_or_notes']) ? $processes['comment_or_notes'] : null
                        ];
                        
                        $processes['process_steps_generated_filename'] = $generated_filename;

                        $files_to_upload[] = [
                            'file' => $processes['process_steps_file'],
                            'generated_filename' => $generated_filename
                        ];

                        $processes['process_steps_file'] = $processes['process_steps_file']->getClientOriginalName();
                        

                    }

                    if(!$this->db->table($this->table_processes)->insert($processing_process_data)){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Save Data");
                    }
                    else{
                        
                        $processes_query_result = $this->db->table($this->table_processes)->where('processing_id', $payload['id'])->get();

                        $equipment_details_data = [];

                        foreach($payload['processing_processes'] as &$pc){
                            foreach($processes_query_result as $pq_result){
                                if($pc['process_steps_generated_filename'] == $pq_result->process_steps_generated_filename){
                                    $pc['id'] = $pq_result->id; 
                                }
                            }

                            foreach($pc['processing_equipment_details'] as &$equipment_details){
                                $equipment_details['processing_process_id'] = $pc['id'];

                                $equipment_details_data[] = [
                                    "processing_process_id" =>  $pc['id'],
                                    "equipment_name" => $equipment_details['equipment_name'],
                                    "verification" => $equipment_details['verification'],
                                    "verification_status" =>$equipment_details['verification_status'],
                                    "date_code_verification" => isset($equipment_details['date_code_verification']) ? $equipment_details['date_code_verification'] : null,
                                    "qc_inspector" => isset($equipment_details['qc_inspector']) ? $equipment_details['qc_inspector'] : null,
                                    "issue_identified" => isset($equipment_details['isse_identified']) ? $equipment_details['issue_identified'] : null,
                                    "corrected_by" => isset($equipment_details['corrected_by']) ? $equipment_details['corrected_by'] : null,
                                    "corrective_action" => isset($equipment_details['corrective_action']) ? $equipment_details['corrective_action'] : null,
                                    "corrected_date_and_time" => isset($equipment_details['corrected_date_and_time']) ? $equipment_details['corrected_date_and_time'] : null
                                ];
                            }
                        }

                        if(!$this->db->table($this->table_equipments)->insert($equipment_details_data)){
                            $this->db->rollback();
                            return $this->response->errorResponse("Can't Save Data");
                        }
                        else{

                            foreach($files_to_delete as $f_t_d){
                                if(!$this->file->deleteFile($f_t_d['file'], $this)){
                                    $this->db->rollback();
                                    return $this->response->errorResponse("Can't upload File");
                                }
                            }
                            
                            foreach($files_to_upload as $file){
                                if(!$this->file->saveFile($file['file'], $file['generated_filename'], $this)){
                                    $this->db->rollback();
                                    return $this->response->errorResponse("Can't upload File");
                                }
                            }

                            $this->db->commit();
                            return $this->response->buildApiResponse($payload, $this->response_column);
                            
                        }
                    }
                }
                else{

                    $this->db->commit();
                    return $this->response->buildApiResponse($payload,$this->response_column);

                }
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

    }

    public function upload(Request $request, $id){

    }
}