<?php

/**
 *
 * replace the SystemName based on the Folder
 *
 */

namespace App\Http\Controllers\CigProduction;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Helpers\ResponseHelper;
use Illuminate\Validation\ValidationException;

/**
 *
 * replace the ApiController based on the module name + ApiController ex. moduleNameApiController
 *
 */
class ProductionPlanController extends Controller {
    public const GET_PERMISSION_ALIAS = "GET_API_ALIAS";

    public const POST_PERMISSION_ALIAS = "POST_API_ALIAS";

    public const PUT_PERMISSION_ALIAS = null;

    public const DELETE_PERMISSION_ALIAS = null;

    public const FILE_UPLOAD_PERMISSION_ALIAS = null;



    protected $response;
    protected $db;
    public function __construct(Request $request) {
        $this->response = new ResponseHelper($request);
        /**
         *
         *  Rename system_database_connection based on preferred database on database.php
         *
         */
        $this->db = DB::connection("cig_production_connection");
    }

    /**
     *
     * modify accepted parameters
     *
     * */
    protected $accepted_parameters = [];

    /**
     *
     * modify required fields based on accepted parameters
     *
     * */
    protected $required_fields = [];

    /**
     *
     * modify response column
     *
     * */
    protected $response_columns = [
        'id',
        'batch_number',
        'product_id',
        'quantity',
        'start_date_and_time',
        'end_date_and_time',
        'customer_name',
        'is_deleted',
        'status',
        'is_archived',
    ];

    /**
     *
     * modify table name
     *
     * */
    protected $planning_table = 'production_planning';
    protected $material_details_table = 'production_material_details';
    protected $activity_logs_table = 'production_activity_logs';
    // protected $product_table = 'product'; //uncomment if product table already existed

    // REUSABLE VALIDATION RULES
    protected function validateProductionPlanData(Request $request) {
        return $request->validate([
            'id' => 'nullable|integer',
            'batch_number' => 'required|string',
            'product_id' => 'required|string',
            'description' => 'nullable|string',
            'quantity' => 'required|integer',
            'start_date_and_time' => 'required|date',
            'end_date_and_time' => 'required|date',
            'customer_name' => 'required|string',
            'purchase_order_number' => 'required|string',
            'sales_order_number' => 'required|string',
            'comments_notes' => 'nullable|string',
            'material_details' => 'required|array',
            'material_details.*.id' => 'nullable|integer',
            'material_details.*.production_plan_id' => 'nullable|integer',
            'material_details.*.material_id' => 'required|string',
            'material_details.*.supplier_id' => 'required|integer',
            'material_details.*.description' => 'nullable|string',
            'material_details.*.uom' => 'required|string',
            'material_details.*.lot_number' => 'required|string',
            'material_details.*.amount' => 'required|numeric',
            'material_details.*.batch' => 'required|integer',
            'material_details.*.amount_issued_date_and_time' => 'nullable|date',
            'material_details.*.pick_location' => 'required|string',
            'activity_logs' => 'nullable|array',
            'activity_logs.*.personnel_id' => 'required|integer',
            'activity_logs.*.action' => 'required|integer',
            'activity_logs.*.date_and_time' => 'required|date',
        ]);
    }


    public function getPlan($id = null) {
        try {
            // Fetch by ID
            if ($id) {
                // add additional columns for ID-specific response
                $this->response_columns = array_merge($this->response_columns, [
                    'description',
                    'purchase_order_number',
                    'sales_order_number',
                    'comments_notes',
                    'updated_by',
                    'updated_at',
                    'material_details',
                    'activity_logs',
                ]);

                // Fetch the production plan with related details and activity logs
                $query_result = $this->db->table($this->planning_table)
                    ->where("{$this->planning_table}.id", $id)
                    ->get();

                if ($query_result->isEmpty()) {
                    return $this->response->errorResponse('No data found for the given ID.');
                }

                // Fetch related material details and activity logs
                $material_details = $this->db->table($this->material_details_table)
                    ->where("{$this->material_details_table}.production_plan_id", $id)
                    ->get();

                $activity_logs = $this->db->table($this->activity_logs_table)
                    ->where("{$this->activity_logs_table}.production_plan_id", $id)
                    ->select("id", "production_plan_id", "personnel_id", "action", "date_and_time")
                    ->get();

                // Add related data to the result
                $query_result[0]->material_details = $material_details->toArray();
                $query_result[0]->activity_logs = $activity_logs->toArray();

                // Dynamically include additional columns for ID-specific response
                $response_columns = array_merge(
                    $this->response_columns,
                    ['material_details', 'activity_logs']
                );

                return $this->response->buildApiResponse($query_result, $response_columns);
            }

            // Fetch all production plans (when no ID is provided)
            $query_result = $this->db->table($this->planning_table)->get();

            // Use response_columns for non-ID response
            return $this->response->buildApiResponse($query_result, $this->response_columns);

            // use the code below if products table already existed

            // // Fetch all production plans (when no ID is provided)
            // $query_result = $this->db->table($this->planning_table)
            //     ->leftJoin($this->product_table, "{$this->planning_table}.product_id", '=', "{$this->product_table}.id")
            //     ->select("{$this->planning_table}.*", "{$this->product_table}.product_name", "{$this->product_table}.product_code")
            //     ->get();

            // // Use response_columns for non-ID response
            // $response_columns = array_merge($this->response_columns, ['product_name', 'product_code']);
            // return $this->response->buildApiResponse($query_result, $response_columns);

        } catch (QueryException $e) {
            return $this->response->errorResponse($e);
        } catch (Exception $e) {
            return $this->response->errorResponse($e);
        }
    }

    public function postPlan(Request $request) {
        try {
            // Required fields
            $this->required_fields = [
                'batch_number',
                'product_id',
                'quantity',
                'start_date_and_time',
                'end_date_and_time',
                'customer_name',
                'purchase_order_number',
                'sales_order_number',
                'comments_notes',
                'material_details' => [
                    'material_id',
                    'supplier_id',
                    'description',
                    'uom',
                    'lot_number',
                    'amount',
                    'batch',
                    'amount_issued_date_and_time',
                    'pick_location',
                ],
            ];

            // Accepted parameters
            $this->accepted_parameters = [
                'batch_number',
                'product_id',
                'description',
                'quantity',
                'start_date_and_time',
                'end_date_and_time',
                'customer_name',
                'purchase_order_number',
                'sales_order_number',
                'comments_notes',
                'material_details' => [
                    'material_id',
                    'supplier_id',
                    'description',
                    'uom',
                    'lot_number',
                    'amount',
                    'batch',
                    'amount_issued_date_and_time',
                    'pick_location',
                ]
            ];

            $errors = [
                'invalid_parameters' => [],
                'required_fields' => [],
            ];

            // Validate accepted parameters
            foreach ($request->all() as $key => $value) {
                if (!isset($this->accepted_parameters[$key])) {
                    if (!in_array($key, $this->accepted_parameters, true)) {
                        $errors['invalid_parameters'][] = "Parameter '{$key}' is not accepted.";
                    }
                }

                if (is_array($value) && isset($this->accepted_parameters[$key]) && is_array($this->accepted_parameters[$key])) {
                    foreach ($value as $subValue) {
                        if (!is_array($subValue)) {
                            $errors['invalid_parameters'][] = "Parameter '{$key}' must be an array of objects.";
                        }
                        foreach ($subValue as $nestedKey => $nestedValue) {
                            if (!in_array($nestedKey, $this->accepted_parameters[$key])) {
                                $errors['invalid_parameters'][] = "Parameter '{$nestedKey}' in '{$key}' is not accepted.";
                            }
                        }
                    }
                }
            }

            // Validate required fields
            foreach ($this->required_fields as $key => $value) {
                if (is_array($value)) {
                    // Handle nested required fields like 'material_details'
                    $parentField = $key;

                    if (!$request->has($parentField)) {
                        $errors['required_fields'][$parentField] = "{$parentField} is required.";
                    } else {
                        foreach ($request->get($parentField, []) as $index => $nestedItem) {
                            foreach ($value as $nestedField) {
                                if (!isset($nestedItem[$nestedField])) {
                                    $errors['required_fields']["{$parentField}[{$index}].{$nestedField}"] = "{$nestedField} is required in {$parentField}.";
                                }
                            }
                        }
                    }
                } else {
                    // Handle top-level required fields
                    if (!$request->has($value)) {
                        $errors['required_fields'][$value] = "{$value} is required.";
                    }
                }
            }

            // If there are errors, return the response
            if (!empty($errors['invalid_parameters']) || !empty($errors['required_fields'])) {
                return $this->response->errorResponse($errors);
            }

            // Validate the input data using the reusable method
            $validatedData = $this->validateProductionPlanData($request);

            $this->db->beginTransaction();

            // Insert the production plan
            $productionPlanId = $this->db->table($this->planning_table)
                ->insertGetId([
                    'batch_number' => $validatedData['batch_number'],
                    'product_id' => $validatedData['product_id'],
                    'description' => $validatedData['description'],
                    'quantity' => $validatedData['quantity'],
                    'start_date_and_time' => $validatedData['start_date_and_time'],
                    'end_date_and_time' => $validatedData['end_date_and_time'],
                    'customer_name' => $validatedData['customer_name'],
                    'purchase_order_number' => $validatedData['purchase_order_number'],
                    'sales_order_number' => $validatedData['sales_order_number'],
                    'comments_notes' => $validatedData['comments_notes'],
                    'status' => 0, // Assuming the default status is 0
                    'is_archived' => 0,  // Assuming the default is_archived is 0
                ]);

            // Insert the material details
            foreach ($validatedData['material_details'] as $materialDetail) {
                $this->db->table($this->material_details_table)
                    ->insert([
                        'production_plan_id' => $productionPlanId,
                        'material_id' => $materialDetail['material_id'],
                        'supplier_id' => $materialDetail['supplier_id'],
                        'description' => $materialDetail['description'],
                        'uom' => $materialDetail['uom'],
                        'lot_number' => $materialDetail['lot_number'],
                        'amount' => $materialDetail['amount'],
                        'batch' => $materialDetail['batch'],
                        'amount_issued_date_and_time' => $materialDetail['amount_issued_date_and_time'],
                        'pick_location' => $materialDetail['pick_location'],
                        'is_deleted' => 0, // Assuming the default is_deleted is 0
                    ]);
            }

            // Insert the activity logs
            $this->db->table($this->activity_logs_table)
                ->insert([
                    'production_plan_id' => $productionPlanId,
                    'personnel_id' => 1, // Assuming you have the user ID from the request
                    'action' => 0, // Assuming the action for creation is 0
                    'date_and_time' => now(),
                ]);

            $this->db->commit();

            return $this->response->successResponse('Production plan created successfully.');
        } catch (QueryException $e) {
            $this->db->rollBack();
            return $this->response->errorResponse($e);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->response->errorResponse($e);
        }
    }


    public function editPlanDetails(Request $request, $id) {
        try {
            // Required fields
            $this->required_fields = [
                'batch_number',
                'product_id',
                'quantity',
                'start_date_and_time',
                'end_date_and_time',
                'customer_name',
                'purchase_order_number',
                'sales_order_number',
                'comments_notes',
                'material_details' => [
                    'material_id',
                    'supplier_id',
                    'description',
                    'uom',
                    'lot_number',
                    'amount',
                    'batch',
                    'amount_issued_date_and_time',
                    'pick_location',
                ],
            ];

            $this->accepted_parameters = [
                'id',
                'batch_number',
                'product_id',
                'description',
                'quantity',
                'start_date_and_time',
                'end_date_and_time',
                'customer_name',
                'purchase_order_number',
                'sales_order_number',
                'comments_notes',
                'material_details' => [
                    'id',
                    'production_plan_id',
                    'material_id',
                    'supplier_id',
                    'description',
                    'uom',
                    'lot_number',
                    'amount',
                    'batch',
                    'amount_issued_date_and_time',
                    'pick_location',
                ]
            ];

            $errors = [
                'invalid_parameters' => [],
                'required_fields' => [],
            ];

            // Validate the input data using the reusable method
            $validatedData = $this->validateProductionPlanData($request);

            $this->db->beginTransaction();

            // Update the production_planning table
            $this->db->table($this->planning_table)
                ->where('id', $id)
                ->update([
                    'batch_number' => $validatedData['batch_number'],
                    'product_id' => $validatedData['product_id'],
                    'description' => $validatedData['description'],
                    'quantity' => $validatedData['quantity'],
                    'start_date_and_time' => $validatedData['start_date_and_time'],
                    'end_date_and_time' => $validatedData['end_date_and_time'],
                    'customer_name' => $validatedData['customer_name'],
                    'purchase_order_number' => $validatedData['purchase_order_number'],
                    'sales_order_number' => $validatedData['sales_order_number'],
                    'comments_notes' => $validatedData['comments_notes'],
                ]);

            // Handle material_details updates
            if (isset($validatedData['material_details']) && is_array($validatedData['material_details'])) {
                $updatedMaterialIds = [];

                foreach ($validatedData['material_details'] as $material) {
                    // Check if this material detail has an ID (indicating it already exists)
                    if (isset($material['id']) && $material['id']) {
                        // Update existing material using the 'id'
                        $this->db->table($this->material_details_table)
                            ->where('id', $material['id'])
                            ->where('production_plan_id', $id) // Ensures the record belongs to the plan
                            ->update([
                                'material_id' => $material['material_id'],
                                'supplier_id' => $material['supplier_id'],
                                'description' => $material['description'],
                                'uom' => $material['uom'],
                                'lot_number' => $material['lot_number'],
                                'amount' => $material['amount'],
                                'batch' => $material['batch'],
                                'amount_issued_date_and_time' => $material['amount_issued_date_and_time'],
                                'pick_location' => $material['pick_location'],
                            ]);

                        // Add the existing ID to the updated list
                        $updatedMaterialIds[] = $material['id'];
                    } else {
                        // Insert new material detail
                        $newId = $this->db->table($this->material_details_table)->insertGetId([
                            'production_plan_id' => $id,
                            'material_id' => $material['material_id'],
                            'supplier_id' => $material['supplier_id'],
                            'description' => $material['description'],
                            'uom' => $material['uom'],
                            'lot_number' => $material['lot_number'],
                            'amount' => $material['amount'],
                            'batch' => $material['batch'],
                            'amount_issued_date_and_time' => $material['amount_issued_date_and_time'],
                            'pick_location' => $material['pick_location'],
                            'is_deleted' => 0, // Assuming the default is_deleted is 0
                        ]);
                        $updatedMaterialIds[] = $newId; // Track the newly generated ID
                    }
                }

                // Remove materials that are not in the updatedMaterialIds list
                $this->db->table($this->material_details_table)
                    ->where('production_plan_id', $id)
                    ->whereNotIn('id', $updatedMaterialIds)
                    ->delete();
            }

            // Validate accepted parameters
            foreach ($request->all() as $key => $value) {
                if (!isset($this->accepted_parameters[$key])) {
                    if (!in_array($key, $this->accepted_parameters, true)) {
                        $errors['invalid_parameters'][] = "Parameter '{$key}' is not accepted.";
                    }
                }

                if (is_array($value) && isset($this->accepted_parameters[$key]) && is_array($this->accepted_parameters[$key])) {
                    foreach ($value as $subValue) {
                        if (!is_array($subValue)) {
                            $errors['invalid_parameters'][] = "Parameter '{$key}' must be an array of objects.";
                        }
                        foreach ($subValue as $nestedKey => $nestedValue) {
                            if (!in_array($nestedKey, $this->accepted_parameters[$key])) {
                                $errors['invalid_parameters'][] = "Parameter '{$nestedKey}' in '{$key}' is not accepted.";
                            }
                        }
                    }
                }
            }

            // Validate required fields
            foreach ($this->required_fields as $key => $value) {
                if (is_array($value)) {
                    // Handle nested required fields like 'material_details'
                    $parentField = $key;

                    if (!$request->has($parentField)) {
                        $errors['required_fields'][$parentField] = "{$parentField} is required.";
                    } else {
                        foreach ($request->get($parentField, []) as $index => $nestedItem) {
                            foreach ($value as $nestedField) {
                                if (!isset($nestedItem[$nestedField])) {
                                    $errors['required_fields']["{$parentField}[{$index}].{$nestedField}"] = "{$nestedField} is required in {$parentField}.";
                                }
                            }
                        }
                    }
                } else {
                    // Handle top-level required fields
                    if (!$request->has($value)) {
                        $errors['required_fields'][$value] = "{$value} is required.";
                    }
                }
            }

            // If there are errors, return the response
            if (!empty($errors['invalid_parameters']) || !empty($errors['required_fields'])) {
                return $this->response->errorResponse($errors);
            }

            // Commit to the database
            $this->db->commit();

            return $this->response->successResponse('Production plan updated successfully.');
        } catch (QueryException $e) {
            $this->db->rollBack();
            return $this->response->errorResponse($e);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->response->errorResponse($e);
        }
    }

    public function editPlanStatus(Request $request, $id) {
        try {
            // Accepted parameters
            $this->accepted_parameters = [
                'id',
                'status',
            ];

            // Required fields
            $this->required_fields = [
                'id',
                'status',
            ];

            $validatedData = $request->validate([
                'id' => 'required|integer',
                'status' => 'required|integer',
            ]);

            $this->db->beginTransaction();

            // Check if the production plan exists
            $productionPlan = $this->db->table($this->planning_table)
                ->where('id', $id)
                ->first();

            if (!$productionPlan) {
                return $this->response->errorResponse('Production plan not found.');
            }

            // Check if status is valid
            if (!in_array($request->status, [0, 1, 2, 3, 4, 5, 6, 7], true)) {
                return $this->response->errorResponse('Invalid status.');
            }

            // Check if the status has changed
            if ($productionPlan->status !== $validatedData['status']) {
                // Update the production plan status
                $this->db->table($this->planning_table)
                    ->where('id', $id)
                    ->update(['status' => $validatedData['status']]);

                // Insert the activity log
                $this->db->table($this->activity_logs_table)
                    ->insert([
                        'production_plan_id' => $id,
                        'personnel_id' => 1, // Assuming you have the user ID from the request
                        'action' => $validatedData['status'],
                        'date_and_time' => now(),
                    ]);
            } else {
                // Update the production plan status without activity log
                $this->db->table($this->planning_table)
                    ->where('id', $id)
                    ->update(['status' => $validatedData['status']]);
            }

            $this->db->commit();

            return $this->response->successResponse('Production plan status updated successfully.');
        } catch (QueryException $e) {
            $this->db->rollBack();
            return $this->response->errorResponse($e);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->response->errorResponse($e);
        }
    }

    public function archiveProductionPlan(Request $request, $id) {
        try {
            // Accepted Parameters
            $this->accepted_parameters = [
                'id',
                'is_archived',
            ];

            // Required Fields
            $this->required_fields = [
                'id',
                'is_archived',
            ];

            $validatedData = $request->validate([
                'id' => 'required|integer',
                'is_archived' => 'required|integer',
            ]);

            $this->db->beginTransaction();

            // Check if the production plan exists
            $productionPlan = $this->db->table($this->planning_table)
                ->where('id', $id)
                ->first();

            if (!$productionPlan) {
                return $this->response->errorResponse('Production plan not found.');
            }

            // Check if is_archived is valid
            if (!in_array($request->is_archived, [0, 1], true)) {
                return $this->response->errorResponse('Invalid archive status.');
            }

            // Update the production plan archive status
            $this->db->table($this->planning_table)
                ->where('id', $id)
                ->update(['is_archived' => $validatedData['is_archived']]);

            $this->db->commit();

            return $this->response->successResponse('Production plan archive status has changed.');
        } catch (QueryException $e) {
            $this->db->rollBack();
            return $this->response->errorResponse($e);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->response->errorResponse($e);
        }
    }
}
