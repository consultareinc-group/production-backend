<?php

/**
 * 
 * replace the SystemName based on the Folder
 * 
*/
namespace App\Http\Controllers\ProductionManagementSystem\CompoundingMixing;

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

/**
 * 
 * replace the ApiController based on the module name + ApiController ex. moduleNameApiController
 * 
*/
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
        'sop_reference',
        "status",
        "is_archived",
        "search_keyword",
        "compounding_materials"
    ];

    /**
     * 
     * modify required fields based on accepted parameters
     * 
     * */
    protected $required_fields = [
        "production_id",
        "sop_reference",
        "compounding_materials"
    ];

    /**
     * 
     * modify response column
     * 
     * */
    protected $response_column = [
        "id", 
        "production_id",
        "batch_number",
        "status",
        "is_archived",
        "inspection",
        "sop_reference",
        "compounding_materials",
        "compounding_activity_logs",
        "instruction",
        "material_name",
        "reviewer",
        "approver",
        "actual_amount"
    ];

    

    /**
     * 
     * modify table name
     * 
     * */
    protected $table = 'compounding_mixing';
    protected $table_material = 'compounding_materials';
    protected $table_activity_logs = 'compounding_mixing_activity_logs';
    protected $table_production = 'production_planning';
    protected $account_database = 'cig_core_accounts_db';


    public function get(Request $request, $id = null){
        try{

            if($id){
                if($id == 0){
                    return $this->response->errorResponse("ID value cannot be zero!");
                }
                else{
                    $query_result = $this->db->table($this->table_production . ' as pp')
                                             ->join($this->table . ' as cm', 'cm.production_id', '=', 'pp.id') 
                                             ->select(
                                                'cm.id',
                                                'pp.batch_number',
                                                'cm.sop_reference',
                                                'cm.sop_reference_generated_filename',
                                                'cm.is_archived'
                                             )
                                             ->where('cm.id', $id)
                                             ->first();

                    if(empty($query_result)){
                        return $this->response->buildApiResponse($query_result, $this->response_column);
                    }
                    else{
                        $query_result->compounding_materials = $this->db->table($this->table_material)->where('compounding_mixing_id', $query_result->id)->get();
                        $query_result->compounding_activity_logs = $this->db->table($this->table_activity_logs)->where('compounding_mixing_id', $query_result->id)->get();

                        $personnel_ids = $query_result->compounding_activity_logs->pluck('personnel_id')->unique();

                        $personnel_details = $this->account->table('user_information')->where('id',$personnel_ids)->get();

                        if(!empty($personnel_result)){
                            foreach($query_result->compounding_activity_logs as &$logs){
                                $personnel = property_exists($personnel_details, $log->personnel_id) 
                                                            ? $personnel_details->{$log->personnel_id} 
                                                            : null;
                                if ($personnel) {
                                    // Merge first name, middle name, and last name into a full name
                                    $log->personnel_full_name = trim($personnel->first_name . ' ' . $personnel->middle_name . ' ' . $personnel->last_name);
                                }
                            }
                        }
                    }
                }



            }
            if($request->has('offset')){

                $query_result = $this->db->select("
                                                    SELECT 
                                                        pp.batch_number,
                                                        sm.material_name,
                                                        cm.instruction,
                                                        cm.instruction_generated_filename,
                                                        cm.actual_amount,
                                                        -- Concatenate first and last names to get the full name
                                                        CONCAT(rev.first_name, ' ', rev.last_name) AS reviewer,
                                                        CONCAT(app.first_name, ' ', app.last_name) AS approver,
                                                        pp.status,
                                                        pp.is_archived
                                                    FROM tbl_compounding_materials AS cm
                                                    INNER JOIN tbl_compounding_mixing AS mx ON cm.compounding_mixing_id = mx.id
                                                    INNER JOIN tbl_production_planning AS pp ON mx.production_id = pp.id
                                                    LEFT JOIN tbl_supplier_material AS sm ON cm.material_id = sm.ID
                                                    -- Join reviewer and approver names from the other database
                                                    LEFT JOIN {$this->account_database}.tbl_user_information AS rev ON cm.reviewed_by = rev.id
                                                    LEFT JOIN {$this->account_database}.tbl_user_information AS app ON cm.approved_by = app.id
                                                    WHERE cm.id IN (
                                                        SELECT MIN(id)
                                                        FROM tbl_compounding_materials
                                                        GROUP BY compounding_mixing_id
                                                    )
                                                    ORDER BY cm.id DESC
                                                    LIMIT 100 OFFSET ?
                                                ", [trim($request->query('offset', 0), '"')]);


            }

            if($request->has('search_keyword')){
                $search_keyword = trim($request->query('search_keyword', ''), '"');

                $query_result = $this->db->select("
                    SELECT 
                        pp.batch_number,
                        sm.material_name,
                        cm.instruction,
                        cm.actual_amount,
                        -- Concatenate first and last names to get the full name of reviewer and approver
                        CONCAT(rev.first_name, ' ', rev.last_name) AS reviewer,
                        CONCAT(app.first_name, ' ', app.last_name) AS approver,
                        pp.status,
                        pp.is_archived
                    FROM tbl_compounding_materials AS cm
                    INNER JOIN tbl_compounding_mixing AS mx ON cm.compounding_mixing_id = mx.id
                    INNER JOIN tbl_production_planning AS pp ON mx.production_id = pp.id
                    LEFT JOIN tbl_supplier_material AS sm ON cm.material_id = sm.ID
                    -- Join reviewer and approver names from the other database
                    LEFT JOIN {$this->account_database}.tbl_user_information AS rev ON cm.reviewed_by = rev.id
                    LEFT JOIN {$this->account_database}.tbl_user_information AS app ON cm.approved_by = app.id
                    WHERE cm.id IN (
                        SELECT MIN(id)
                        FROM tbl_compounding_materials
                        GROUP BY compounding_mixing_id
                    )
                    AND (
                        pp.batch_number LIKE ? OR
                        sm.material_name LIKE ? OR
                        cm.instruction LIKE ? OR
                        cm.remarks LIKE ?
                    )
                    ORDER BY cm.id DESC
                ", [
                    "%$search_keyword%", // For batch_number
                    "%$search_keyword%", // For material_name
                    "%$search_keyword%", // For instruction
                    "%$search_keyword%"  // For remarks
                ]);


            }
            
            return $this->response->buildApiResponse($query_result, $this->response_column);
            

        }
        catch(QueryException $e){
            return $this->response->errorResponse($e);
        }
        catch(Exception $e) {
            return $this->response->errorResponse($e);
        }
                    
       
    }

    public function post(Request $request){

        $accepted_compounding_material_params = [
            
            'material_id',
            'instruction',
            'actual_amount',
            'standard_duration',
            'start_date_and_time_mixing',
            'end_date_and_time_mixing',
            'reviewed_by',
            'reviewed_date_and_time',
            'approved_by',
            'approved_date_and_time',
            'operator_names',
            'remarks'

        ];

        $required_compounding_material_params = [
   
            'material_id',
            'instruction',
            'actual_amount',
            'standard_duration',
            'start_date_and_time_mixing',
            'end_date_and_time_mixing',
            'reviewed_by',
            'reviewed_date_and_time',
            'approved_by',
            'approved_date_and_time',
            'operator_names',
            'remarks'

        ];

       
        $this->db->beginTransaction();

        $payload = $this->validation->validateRequest($request, $this->accepted_parameters, $this->required_fields);

        //check if the $payload has error validation key 
        if(isset($payload['error_validation'])){
            $this->db->rollback();
            return $this->response->errorResponse($payload['message']);
        }
        try{
            foreach($payload['compounding_materials'] as &$cm){
                $validation_result = $this->validation->validateRequest($cm,$accepted_compounding_material_params,  $required_compounding_material_params);
                if(isset( $validation_result['error_validation'])){
                    $this->db->rollback();
                    return $this->response->errorResponse($validation_result['message']);
                }
            }
    

            $generated_filename = $this->file->generateUniqueFilename($this,$payload['sop_reference']->getClientOriginalName());

            $compounding_data = [
                'production_id' => $payload['production_id'],
                'sop_reference' => $payload['sop_reference']->getClientOriginalName(),
                'sop_reference_generated_filename' => $generated_filename,
                'status' => 0,
                'is_archived' => 0
            ];

            $file_upload[] = [
                'file' => $payload['sop_reference'],
                'generated_filename' => $generated_filename
            ];

            $payload['id'] = $this->db->table($this->table)->insertGetId($compounding_data);
            if($payload['id']){
                
                $compounding_materials_data = [];
                foreach($payload['compounding_materials'] as $cm){

                    $generated_filename = $this->file->generateUniqueFilename($this,$cm['instruction']->getClientOriginalName());

                    $file_upload[] = [
                        'file' => $cm['instruction'],
                        'generated_filename' => $generated_filename
                    ];

                    $compounding_materials_data[] = [
                        'compounding_mixing_id' => $payload['id'],
                        'material_id' => $cm['material_id'],
                        'instruction' => $cm['instruction']->getClientOriginalName(),
                        'instruction_generated_filename' => $generated_filename,
                        'actual_amount' =>$cm['actual_amount'],
                        'standard_duration' => $cm['standard_duration'],
                        'start_date_and_time_mixing' => (new DateTime($cm['start_date_and_time_mixing']))->format('Y-m-d H:i:s'),
                        'end_date_and_time_mixing' => (new DateTime($cm['end_date_and_time_mixing']))->format('Y-m-d H:i:s'),
                        'reviewed_by' => $cm['reviewed_by'],
                        'reviewed_date_and_time' => (new DateTime($cm['reviewed_date_and_time']))->format('Y-m-d H:i:s'),
                        'approved_by' => $cm['approved_by'],
                        'approved_date_and_time' => (new DateTime($cm['approved_date_and_time']))->format('Y-m-d H:i:s'),
                        'operator_names' => $cm['operator_names'],
                        'remarks' => $cm['remarks']
                    ];

                }


                if(!$this->db->table($this->table_material)->insert($compounding_materials_data)){
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save Data");
                }
                else{

                    //save activity logs information
                    $activity_log = [
                        'compounding_mixing_id' => $payload['id'],
                        'personnel_id' => $this->user_info->getUserId(),
                        'action' => $payload['status'],
                        'date_and_time' => now()
                    ];

                    if(!$this->db->table($this->table_activity_logs)->insert($activity_log)){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Save Data");
                    }
                    else{

                        foreach($file_upload as $fup){
                            if(!$this->file->saveFile($fup['file'],$fup['generated_filename'], $this)){
                                $this->db->rollback();
                                return $this->response->errorResponse("Can't Save Data. Unable to Save File");
                            }
                        }
                        $payload = [
                            'id' => $payload['id'],
                            'production_id' => $payload['production_id'],
                            'sop_reference' => $payload['sop_reference']->getClientOriginalName(),
                            'sop_reference_generated_filename' => $generated_filename,
                            'status' => 0,
                            'is_archived' => 0
                        ];
                        $payload['compounding_materials'] = $compounding_materials_data;
                        $payload['compounding_activity_logs'] = $activity_log;
                        

                        $this->db->commit();
                        return $this->response->buildApiResponse($payload, $this->response_column);

                    }

                }
            }
            else{
                $this->db->rollback();
                return $this->response->errorResponse("Can't Save Data");
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

        $accepted_compounding_material_params = [
            
            'material_id',
            'instruction',
            'actual_amount',
            'standard_duration',
            'start_date_and_time_mixing',
            'end_date_and_time_mixing',
            'reviewed_by',
            'reviewed_date_and_time',
            'approved_by',
            'approved_date_and_time',
            'operator_names',
            'remarks'

        ];

        $required_compounding_material_params = [
   
            'material_id',
            'instruction',
            'actual_amount',
            'standard_duration',
            'start_date_and_time_mixing',
            'end_date_and_time_mixing',
            'reviewed_by',
            'reviewed_date_and_time',
            'approved_by',
            'approved_date_and_time',
            'operator_names',
            'remarks'

        ];

       
        $this->db->beginTransaction();

        $payload = $this->validation->validateRequest($request, $this->accepted_parameters, $this->required_fields);

        //check if the $payload has error validation key 
        if(isset($payload['error_validation'])){
            $this->db->rollback();
            return $this->response->errorResponse($payload['message']);
        }
        try{
            if(empty($id)){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update Data");
            }
            if($payload['id'] != $id){
                $this->db->rollback();
                return $this->response->errorResponse("IDs Do not Match!");
            }

            $file_upload = [];

            $files_to_delete = [];

            $compounding_data = [
                "id" => $id,
                "production_id" => $payload['production_id'],
                "sop_reference" => $payload['sop_reference']->getClientOriginalName(),
                "status" => $payload['status'],
                "is_archived" => $payload['is_archived'],
                'sop_reference_generated_filename' => $this->file->generateUniqueFilename($this,$payload['sop_reference']->getClientOriginalName())
            ];

            $compounding_result = $this->db->table($this->table)->where('id', $id)->get()->first();

            $is_equal = true;

            // Check if the file exists in $compounding_result and add to $files_to_delete
            if (isset($compounding_result->sop_reference_generated_filename)) {
                $files_to_delete[] = [
                    'filename' => $compounding_result->sop_reference_generated_filename
                ];
            }

            // Ensure $compounding_result is an array or convert it to one if it's an object
            $compounding_result_array = is_object($compounding_result) ? (array) $compounding_result : $compounding_result;

            // Compare the compounding data with the result
            $is_equal = true;
            foreach ($compounding_data as $key => $value) {
                if (!array_key_exists($key, $compounding_result_array) || $compounding_result_array[$key] != $value) {
                    $is_equal = false;
                    break;
                }
            }
            

            if(!$is_equal){
                if(!$this->db->table($this->table)->where('id', $id)->update($compounding_data)){
                    $this->db->rollback();
                    return $this->response->errorResponse("Unable to Edit Data");
                }
            }else{
                $compounding_data['sop_reference_generated_filename'] = $compounding_result->sop_reference_generated_filename;
            }
            

            $compounding_materials_result = $this->db->table($this->table_material)->where('compounding_mixing_id', $id)->get();

            if($compounding_materials_result){
                foreach($compounding_materials_result as $cmr){
                    $files_to_delete[] = [
                        'filename' => $cmr->instruction_generated_filename
                    ];
                }
            }

            if(!$this->db->table($this->table_material)->where('compounding_mixing_id', $id)->delete()){
                $this->db->rollback();
                return $this->response->errorResponse("Can't Update Compounding Materials");
            }

            foreach($payload['compounding_materials'] as &$cm){
                $validation_result = $this->validation->validateRequest($cm,$accepted_compounding_material_params,  $required_compounding_material_params);
                if(isset( $validation_result['error_validation'])){
                    $this->db->rollback();
                    return $this->response->errorResponse($validation_result['message']);
                }
            }
    

            $generated_filename = $compounding_data['sop_reference_generated_filename'];

            $compounding_data = [
                'production_id' => $payload['id'],
                'sop_reference' => $payload['sop_reference']->getClientOriginalName(),
                'sop_reference_generated_filename' => $compounding_data['sop_reference_generated_filename'],
                'status' => 0,
                'is_archived' => 0
            ];

            $file_upload[] = [
                'file' => $payload['sop_reference'],
                'generated_filename' => $generated_filename
            ];

            if($payload['id']){
                $compounding_materials_data = [];
                foreach($payload['compounding_materials'] as $cm){

                    $generated_filename = $this->file->generateUniqueFilename($this,$cm['instruction']->getClientOriginalName());

                    $file_upload[] = [
                        'file' => $cm['instruction'],
                        'generated_filename' => $generated_filename
                    ];

                    $compounding_materials_data[] = [
                        'compounding_mixing_id' => $payload['id'],
                        'material_id' => $cm['material_id'],
                        'instruction' => $cm['instruction']->getClientOriginalName(),
                        'instruction_generated_filename' => $generated_filename,
                        'actual_amount' =>$cm['actual_amount'],
                        'standard_duration' => $cm['standard_duration'],
                        'start_date_and_time_mixing' => (new DateTime($cm['start_date_and_time_mixing']))->format('Y-m-d H:i:s'),
                        'end_date_and_time_mixing' => (new DateTime($cm['end_date_and_time_mixing']))->format('Y-m-d H:i:s'),
                        'reviewed_by' => $cm['reviewed_by'],
                        'reviewed_date_and_time' => (new DateTime($cm['reviewed_date_and_time']))->format('Y-m-d H:i:s'),
                        'approved_by' => $cm['approved_by'],
                        'approved_date_and_time' => (new DateTime($cm['approved_date_and_time']))->format('Y-m-d H:i:s'),
                        'operator_names' => $cm['operator_names'],
                        'remarks' => $cm['remarks']
                    ];

                }


                if(!$this->db->table($this->table_material)->insert($compounding_materials_data)){
                    $this->db->rollback();
                    return $this->response->errorResponse("Can't Save Data");
                }
                else{

                    foreach($files_to_delete as $ftd){
                        if(!$this->file->deleteFile($ftd['filename'], $this)){
                            $this->db->rollback();
                            return $this->response->errorResponse("Can't Update Data. Can't update Files");
                        }
                    }

                    foreach($file_upload as $fup){
                        if(!$this->file->saveFile($fup['file'],$fup['generated_filename'], $this)){
                            $this->db->rollback();
                            return $this->response->errorResponse("Can't Save Data. Unable to Save File");
                        }
                    }

                    $payload['compounding_materials'] = $compounding_materials_data;
                    $payload['sop_reference'] =  $payload['sop_reference']->getClientOriginalName();
                    $this->db->commit();
                    return $this->response->buildApiResponse($payload, $this->response_column);

                }
            }
            else{
                $this->db->rollback();
                return $this->response->errorResponse("Can't Save Data");
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
        // Insert Code Here
    }

    public function upload(Request $request, $id){
        // Insert Code Here
    }
}