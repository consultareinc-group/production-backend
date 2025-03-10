<?php

/**
 * 
 * replace the SystemName based on the Folder
 * 
*/
namespace App\Http\Controllers\ProductionManagementSystem\PreOperationVerification;

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
        "status",
        "is_archived",
        "search_keyword",
        "preoperation_verifications_inspections"
    ];

    /**
     * 
     * modify required fields based on accepted parameters
     * 
     * */
    protected $required_fields = [
        "production_id",
        "preoperation_verifications_inspections"
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
        "preoperation_verifications_inspections",
        "inspection",
        "sop_reference",
        "sop_reference_generated_filename"
        
    ];

    

    /**
     * 
     * modify table name
     * 
     * */
    protected $table = 'preoperation_verifications';
    protected $table_production = 'production_planning';
    protected $table_inspection = 'preoperation_verifications_inspections';
    // protected $table_personnel = ''



    public function get(Request $request, $id = null){

        try{

            $query_result = [];

            //This section is intended for fetching specific course record
            if ($id) {
                if($id == 0 ){
                    return $this->response->errorResponse("ID value cannot be zero!");
                }
                else{
                    $query_result = $this->db->table($this->table_production . ' as pp')
                                             ->join($this->table . ' as pov', 'pov.production_id', '=', 'pp.id') 
                                             ->select(
                                                'pov.id',
                                                'pp.batch_number',
                                                'pov.is_archived'
                                             )
                                             ->where('pov.id', $id)
                                             ->first();

                    if(empty($query_result)){
                        return $this->response->buildApiResponse($query_result,$this->response_column);
                    }
                    else{
                        $query_result->preoperation_verifications_inspections = $this->db->table($this->table_inspection)->where('preoperation_id',$query_result->id)->get();
                        return $this->response->buildApiResponse($query_result,$this->response_column);

                    }
                }
            }

            // This section is intended for pagination
            if ($request->has('offset')) {
                
                $query_result = $this->db->select("
                                                SELECT 
                                                    tbl_pov.id,
                                                    tbl_pp.batch_number,
                                                    tbl_pov.is_archived,
                                                    tbl_povi.inspection,
                                                    tbl_povi.status,
                                                    tbl_povi.sop_reference,
                                                    tbl_povi.sop_reference_generated_filename
                                                FROM tbl_production_planning AS tbl_pp
                                                INNER JOIN tbl_preoperation_verifications AS tbl_pov
                                                    ON tbl_pov.production_id = tbl_pp.id
                                                LEFT JOIN (
                                                    SELECT *
                                                    FROM tbl_preoperation_verifications_inspections AS sub_povi
                                                    WHERE sub_povi.id = (
                                                        SELECT MIN(inner_povi.id)
                                                        FROM tbl_preoperation_verifications_inspections AS inner_povi
                                                        WHERE inner_povi.preoperation_id = sub_povi.preoperation_id
                                                    )
                                                ) AS tbl_povi
                                                    ON tbl_povi.preoperation_id = tbl_pov.id
                                                    WHERE tbl_pov.is_archived = 0
                                                ORDER BY tbl_pov.id DESC
                                                LIMIT 1000 OFFSET ?
                                            ", [trim($request->query('offset', 0), '"')]);



                
                return $this->response->buildApiResponse($query_result,$this->response_column);
                
            }

            // This section is intended for table search
            if ($request->has('search_keyword')) {

                $search_keyword = $request->query('search_keyword', ''); // Default to an empty string if no keyword is provided

                $query_result = $this->db->select("
                                                SELECT 
                                                    tbl_pov.id,
                                                    tbl_pp.batch_number,
                                                    tbl_pov.is_archived,
                                                    tbl_povi.inspection,
                                                    tbl_povi.status,
                                                    tbl_povi.sop_reference,
                                                    tbl_povi.sop_reference_generated_filename
                                                FROM tbl_production_planning AS tbl_pp
                                                INNER JOIN tbl_preoperation_verifications AS tbl_pov
                                                    ON tbl_pov.production_id = tbl_pp.id
                                                LEFT JOIN (
                                                    SELECT *
                                                    FROM tbl_preoperation_verifications_inspections AS sub_povi
                                                    WHERE sub_povi.id = (
                                                        SELECT MIN(inner_povi.id)
                                                        FROM tbl_preoperation_verifications_inspections AS inner_povi
                                                        WHERE inner_povi.preoperation_id = sub_povi.preoperation_id
                                                    )
                                                ) AS tbl_povi
                                                    ON tbl_povi.preoperation_id = tbl_pov.id
                                                WHERE tbl_pov.is_archived = 0
                                                    AND (
                                                        tbl_pp.batch_number LIKE ? 
                                                        OR tbl_povi.inspection LIKE ? 
                                                        OR tbl_povi.sop_reference LIKE ?
                                                    )
                                                ORDER BY tbl_pov.id DESC
                                                LIMIT 1000
                                            ", [
                                                "%$search_keyword%", // For batch_number
                                                "%$search_keyword%", // For inspection
                                                "%$search_keyword%"  // For sop_reference
                                            ]);


                
                
                return $this->response->buildApiResponse($query_result,$this->response_column);    
            }


            if($request->has('batch_number')){
                $batch_number_keyword = $request->query('batch_number', '');

                $query_result = $this->db->table($this->table_production)
                                         ->select(
                                                'id',
                                                'batch_number'
                                         )
                                         ->where(
                                                'batch_number',
                                                'LIKE',
                                                '%' . $batch_number_keyword . '%'
                                         )
                                         ->get();

                return $this->response->buildApiResponse($query_result, $this->response_column);
            }

            // if($request->has('personnel_name')){
            //     $query_result = $this->account->table()
            // }


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
            $this->db->beginTransaction();

            $preoperation_data = [
                "production_id" => $payload['production_id'],
                "is_archived" => 0,
                "is_deleted" => 0
            ];

            $payload['id'] = $this->db->table($this->table)->insertGetId($preoperation_data);
            
            if(!$payload['id']){
                $this->db->rollback();
                return $this->response->errorResponse("Data not Saved!");
            }
            
            $inspection_data = [];

            $save_files = [];

            if($payload['preoperation_verifications_inspections']){
                foreach($payload['preoperation_verifications_inspections'] as $povi){

                    //get file
                    $file = $povi['sop_reference'];

                    //generate a filename
                    $generated_filename = $this->file->generateUniqueFilename($this,$file->getClientOriginalName());

                    $save_files[] = [
                        'sop_reference' => $file,
                        'sop_reference_generated_filename' => $generated_filename
                    ];

                    // if(!$this->file->saveFile($povi['sop_reference'], $generated_filename, $this)){
                    //     return $this->response->errorResponse("Cant Upload File");
                    // }

                    $ins_d = [
                        'preoperation_id' => $payload['id'],
                        'inspection' => $povi['inspection'],
                        'performed_by' => $povi['performed_by'],
                        'performed_date_and_time' => (new DateTime($povi['performed_date_and_time']))->format('Y-m-d H:i:s'),
                        'status' => $povi['status'],
                        'sop_reference' => $file->getClientOriginalName(),
                        'sop_reference_generated_filename' => $generated_filename,
                        'verified_by' => $povi['verified_by'],
                        'verified_date_and_time' => (new DateTime($povi['verified_date_and_time']))->format('Y-m-d H:i:s'),
                        'observation' => isset($povi['observation'])?$povi['observation'] :null,
                        'corrective_action' => isset($povi['corrective_action'])?$povi['corrective_action'] :null,
                        'corrected_by' => isset($povi['corrected_by'])?$povi['corrected_by'] :null,
                        'corrected_date_and_time' => isset($povi['corrected_date_and_time'])? (new DateTime($povi['corrected_date_and_time']))->format('Y-m-d H:i:s') :null,
    
                    ];
                    $inspection_data[] = $ins_d;

                }
            }

            if($this->db->table($this->table_inspection)->insert($inspection_data)){

                foreach($save_files as $sf){
                      if(!$this->file->saveFile($sf['sop_reference'], $sf['sop_reference_generated_filename'], $this)){
                        return $this->response->errorResponse("Cant Upload File");
                    }
                }

                $this->db->commit();
                $payload['preoperation_verifications_inspections'] = $inspection_data;

                return $this->response->buildApiResponse($payload, $this->response_column);
            }
            else{
                $this->db->rollback();
                return $this->response->errorResponse("Cant Save Data");
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

        $request_keys = array_keys($request->all());
        sort($request_keys); 
        sort($for_archived);

        $edit_request = $request->all();

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
        try{
            if(empty($id)){
                return $this->response->errorResponse("No ID Found!");
            }
            if($payload['id'] != $id){

                return $this->response->errorResponse("IDs Do not Match!");
            }

            $this->db->beginTransaction();

            $preoperation_data = [
                "id" => $id,
                "production_id" => $payload['production_id']
            ];

            if(!$this->db->table($this->table)->where('id', $id)->where($preoperation_data)->exists()){
                if(!$this->db->table($this->table)->where('id', $id)->update($preoperation_data)){
                    $this->db->rollback();
                    return $this->response->errorResponse("Unable to Edit Data");
                }
            }


            $get_inspection_data = $this->db->table($this->table_inspection)->where('preoperation_id',$id)->get();
            
            if(!empty($get_inspection_data)){
                foreach($get_inspection_data as $inspection){
                    if(!$this->file->deleteFile($inspection->sop_reference_generated_filename, $this)){
                        $this->db->rollback();
                        return $this->response->errorResponse("Can't Delete File!");
                    }
                }
            }

            if(!$this->db->table($this->table_inspection)->where('preoperation_id',$id)->delete()){
                $this->rollback();
                return $this->response->errorResponse("Can't Update data");
            }
            
            $inspection_data = [];

            if($payload['preoperation_verifications_inspections']){
                foreach($payload['preoperation_verifications_inspections'] as $povi){

                    //get file
                    $file = $povi['sop_reference'];

                    //generate a filename
                    $generated_filename = $this->file->generateUniqueFilename($this,$file->getClientOriginalName());

                    if(!$this->file->saveFile($povi['sop_reference'], $generated_filename, $this)){
                        return $this->response->errorResponse("Cant Upload File");
                    }

                    $ins_d = [
                        'preoperation_id' => $payload['id'],
                        'inspection' => $povi['inspection'],
                        'performed_by' => $povi['performed_by'],
                        'performed_date_and_time' => (new DateTime($povi['performed_date_and_time']))->format('Y-m-d H:i:s'),
                        'status' => $povi['status'],
                        'sop_reference' => $file->getClientOriginalName(),
                        'sop_reference_generated_filename' => $generated_filename,
                        'verified_by' => $povi['verified_by'],
                        'verified_date_and_time' => (new DateTime($povi['verified_date_and_time']))->format('Y-m-d H:i:s'),
                        'observation' => isset($povi['observation'])?$povi['observation'] :null,
                        'corrective_action' => isset($povi['corrective_action'])?$povi['corrective_action'] :null,
                        'corrected_by' => isset($povi['corrected_by'])?$povi['corrected_by'] :null,
                        'corrected_date_and_time' => isset($povi['corrected_date_and_time'])? (new DateTime($povi['corrected_date_and_time']))->format('Y-m-d H:i:s') :null,
    
                    ];
                    $inspection_data[] = $ins_d;
                }
            }

            if($this->db->table($this->table_inspection)->insert($inspection_data)){
                $this->db->commit();
                $payload['preoperation_verifications_inspections'] = $inspection_data;

                return $this->response->buildApiResponse($payload, $this->response_column);
            }
            else{
                $this->db->rollback();
                return $this->response->errorResponse("Cant Save Data");
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