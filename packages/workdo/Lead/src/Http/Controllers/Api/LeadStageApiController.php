<?php

namespace Workdo\Lead\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Workdo\Lead\Models\LeadStage;
use Workdo\Lead\Models\Pipeline;
use Workdo\Lead\Traits\LeadControllerHelpers;

class LeadStageApiController extends Controller
{
    use ApiResponseTrait, LeadControllerHelpers;

    public function index(Request $request)
    {
        try {
            $leadstages = LeadStage::select('id', 'name', 'order', 'pipeline_id')
                ->where('pipeline_id', $request->pipeline_id)
                ->where('created_by', creatorId())
                ->latest()
                ->get();

            $pipelines = Pipeline::where('created_by', creatorId())
                ->where('id', $request->pipeline_id)
                ->select('id', 'name')
                ->first();

            $data = [
                'pipeline_name' => $pipelines,
                'lead_stages'   => $leadstages,
            ];

            return $this->successResponse($data, 'Lead stages retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong');
        }
    }

    public function leadstageCreateAndUpdate(Request $request)
    {
        try {
            if ($request->lead_stage_id) {
                return $this->updateLeadstage($request);
            } else {
                return $this->createLeadstage($request);
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong');
        }
    }
    public function createLeadstage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'        => 'required|max:100',
                'pipeline_id' => ['required', 'integer', LeadControllerHelpers::tenantExists('pipelines', 'id')],
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $leadstage = DB::transaction(function () use ($request) {
                $maxOrder = LeadStage::where('pipeline_id', $request->pipeline_id)
                    ->where('created_by', creatorId())
                    ->max('order') ?? 0;

                $leadstage              = new LeadStage();
                $leadstage->name        = $request->name;
                $leadstage->pipeline_id = $request->pipeline_id;
                $leadstage->order       = $maxOrder + 1;
                $leadstage->creator_id  = Auth::id();
                $leadstage->created_by  = creatorId();
                $leadstage->save();

                return $leadstage;
            });

            return $this->successResponse($leadstage, 'Lead Stage created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong');
        }
    }

    public function updateLeadstage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'lead_stage_id' => ['required', LeadControllerHelpers::tenantExists('lead_stages', 'id')],
                'name'          => 'required|string',
                'pipeline_id'   => ['required', LeadControllerHelpers::tenantExists('pipelines', 'id')],
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $leadstage = LeadStage::where('created_by', creatorId())->findOrFail($request->lead_stage_id);

            DB::transaction(function () use ($leadstage, $request) {
                $oldPipelineId = $leadstage->pipeline_id;
                $newPipelineId = $request->pipeline_id;
                $oldOrder      = $leadstage->order;

                if ($oldPipelineId != $newPipelineId) {
                    $maxOrder = LeadStage::where('pipeline_id', $newPipelineId)
                        ->where('created_by', creatorId())
                        ->max('order') ?? 0;

                    $leadstage->order = $maxOrder + 1;

                    LeadStage::where('pipeline_id', $oldPipelineId)
                        ->where('created_by', creatorId())
                        ->where('order', '>', $oldOrder)
                        ->decrement('order');
                }

                $leadstage->name = $request->name;
                $leadstage->pipeline_id = $newPipelineId;
                $leadstage->save();
            });

            return $this->successResponse('', 'Lead stage updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong');
        }
    }
}
