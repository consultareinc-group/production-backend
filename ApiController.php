<?php

/**
 * 
 * replace the SystemName based on the Folder
 * 
*/
namespace App\Http\Controllers\SystemName;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Helpers\ResponseHelper;

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
    protected $db;
    public function __construct(Request $request)
    {
        $this->response = new ResponseHelper($request);
        /**
         * 
         *  Rename system_database_connection based on preferred database on database.php
         * 
        */
        $this->db = DB::connection("system_database_connection");
    }

     /**
     * 
     * modify accepted parameters
     * 
     * */
    protected $accepted_parameters = [
        "id", 
        "accepted_parameter1",
        "accepted_parameter2",
        "search_keyword"
        
    ];

    /**
     * 
     * modify required fields based on accepted parameters
     * 
     * */
    protected $required_fields = [
        "id",
        "accepted_parameter1", 
    ];

    /**
     * 
     * modify response column
     * 
     * */
    protected $response_column = [
       "id",
       "response_column1",
       "response_column2",
    ];

    /**
     * 
     * modify table name
     * 
     * */
    protected $table = 'table_name';



    public function get(Request $request, $id = null){

        try{
            if($id){
                //if id has value
            }
            $search = $request->query('search_keyword');

            if($search){
                // if search has value
                //put some query
            }
            /**
             * 
             *  if there is no id and search, the query below will execute
             * 
             *  edit query below based on module requirements
             * 
             */
            $query_result = $this->db->table($this->table)->get();
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
        
        $payload = $request->all();
        if(!empty($payload)){
            foreach ($payload as $field => $value) {
                if (!in_array($field, $this->accepted_parameters)) {
                    return $this->response->invalidParameterResponse();
                }
            }
        }

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

    public function put(Request $request, $id){


        $payload = $request->all();
        if(!empty($payload)){
            foreach ($payload as $field => $value) {
                if (!in_array($field, $this->accepted_parameters)) {
                    return $this->response->invalidParameterResponse();
                }
            }
        }

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