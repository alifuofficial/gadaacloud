<?php

namespace Workdo\Recruitment\Http\Controllers;

use App\Models\EmailTemplate;
use Workdo\Recruitment\Models\Offer;
use Workdo\Recruitment\Http\Requests\StoreOfferRequest;
use Workdo\Recruitment\Http\Requests\UpdateOfferRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Recruitment\Models\Candidate;
use Workdo\Recruitment\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Workdo\Recruitment\Http\Requests\ConvertToEmployeeRequest;
use Illuminate\Support\Facades\Mail;
use Workdo\Hrm\Models\Branch;
use Workdo\Hrm\Models\Department;
use Workdo\Hrm\Models\Designation;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\EmployeeDocument;
use Workdo\Hrm\Models\EmployeeDocumentType;
use Workdo\Hrm\Models\Shift;
use Workdo\Recruitment\Models\Job;
use Workdo\Recruitment\Models\OfferLetter;
use Workdo\Recruitment\Events\CreateOffer;
use Workdo\Recruitment\Events\UpdateOffer;
use Workdo\Recruitment\Events\DestroyOffer;
use Workdo\Recruitment\Events\UpdateOfferApprovalStatus;
use Workdo\Recruitment\Events\ConvertOfferToEmployee;
use Workdo\Recruitment\Services\RecruitmentHrmIntegrationService;
use Workdo\Recruitment\Traits\RecruitmentControllerHelpers;

class OfferController extends Controller
{
    use RecruitmentControllerHelpers;
    public function index()
    {
        if (Auth::user()->can('manage-offers')) {
            $offers = Offer::query()
                ->with(['candidate', 'job', 'approvedBy', 'department'])
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-offers')) {
                        $q->where('offers.created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-offers')) {
                        $q->where('offers.creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('position'), function ($q) {
                    $searchTerm = request('position');
                    $q->where('offers.position', 'like', '%' . $searchTerm . '%');
                })
                ->when(request('candidate_id') && request('candidate_id') !== 'all', fn($q) => $q->where('offers.candidate_id', request('candidate_id')))
                ->when(request('start_date'), fn($q) => $q->where('offers.start_date', '>=', request('start_date')))
                ->when(request('expiration_date'), fn($q) => $q->where('offers.expiration_date', '<=', request('expiration_date')))
                ->when(request('status') !== null && request('status') !== '', fn($q) => $q->where('offers.status', request('status')))
                ->when(request('approval_status') && request('approval_status') !== 'all', fn($q) => $q->where('offers.approval_status', request('approval_status')))
                ->when(request('offer_date'), fn($q) => $q->whereDate('offers.offer_date', request('offer_date')))
                ->when(request('sort'), function ($q) {
                    $sort = request('sort');
                    $direction = strtolower((string) request('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
                    $allowedSorts = ['offer_date', 'start_date', 'expiration_date', 'salary', 'position', 'status', 'approval_status', 'created_at'];

                    if ($sort === 'candidate_id') {
                        $q->join('candidates', 'offers.candidate_id', '=', 'candidates.id')
                            ->orderBy('candidates.first_name', $direction)
                            ->orderBy('candidates.last_name', $direction)
                            ->select('offers.*');
                    } else {
                        $col = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
                        $q->orderBy('offers.' . $col, $direction);
                    }
                }, fn($q) => $q->latest())
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            // Add download URLs to offers
            $offers->getCollection()->transform(function ($offer) {
                $offer->download_url = route('recruitment.offers.download', Crypt::encrypt($offer->id));
                return $offer;
            });

            return Inertia::render('Recruitment/Offers/Index', [
                'offers' => $offers,
                'candidates' => Candidate::where('created_by', creatorId())
                    ->where(function ($query) {
                        $query->whereHas('candidateAssessments', function ($q) {
                            $q->where('pass_fail_status', '0'); // Pass status
                        })->orWhereHas('interviewFeedbacks', function ($q) {
                            $q->whereIn('recommendation', [0, 1]); // Strong Hire & Hire
                        });
                    })
                    ->select('id', 'first_name', 'last_name')
                    ->get()
                    ->map(function ($candidate) {
                        return [
                            'id' => $candidate->id,
                            'name' => $candidate->first_name . ' ' . $candidate->last_name
                        ];
                    }),
                'jobpostings' => JobPosting::where('created_by', creatorId())->where('is_published', 1)->where('status', 'active')->select('id', 'title')->get()->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'name' => $job->title
                    ];
                }),
                'users' => User::emp()->where('created_by', creatorId())->select('id', 'name')->get(),
                'departments' => Department::where('created_by', creatorId())->select('id', 'department_name as name')->get(),
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function create()
    {
        if (Auth::user()->can('create-offers')) {
            return Inertia::render('Recruitment/Offers/Create', [
                'candidates' => Candidate::where('created_by', creatorId())
                    ->where(function ($query) {
                        $query->whereHas('candidateAssessments', function ($q) {
                            $q->where('pass_fail_status', '0'); // Pass status
                        })->orWhereHas('interviewFeedbacks', function ($q) {
                            $q->whereIn('recommendation', [0, 1]); // Strong Hire & Hire
                        });
                    })
                    ->whereNotIn('id', function ($query) {
                        $query->select('candidate_id')
                            ->from('offers')
                            ->where('converted_to_employee', true);
                    })
                    ->select('id', 'first_name', 'last_name')
                    ->get()
                    ->map(function ($candidate) {
                        return [
                            'id' => $candidate->id,
                            'name' => $candidate->first_name . ' ' . $candidate->last_name
                        ];
                    }),
                'users' => User::emp()->where('created_by', creatorId())->select('id', 'name')->get(),
                'departments' => Department::where('created_by', creatorId())->select('id', 'department_name as name')->get(),
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function edit(Offer $offer)
    {
        if (Auth::user()->can('edit-offers')) {
            return Inertia::render('Recruitment/Offers/Edit', [
                'offer' => $offer->load(['candidate', 'job', 'approvedBy']),
                'candidates' => Candidate::where('created_by', creatorId())
                    ->where(function ($query) use ($offer) {
                        $query->whereHas('candidateAssessments', function ($q) {
                            $q->where('pass_fail_status', '0'); // Pass status
                        })->orWhereHas('interviewFeedbacks', function ($q) {
                            $q->whereIn('recommendation', [0, 1]); // Strong Hire & Hire
                        });
                    })
                    ->where(function ($query) use ($offer) {
                        $query->whereNotIn('id', function ($subQuery) {
                            $subQuery->select('candidate_id')
                                ->from('offers')
                                ->where('converted_to_employee', true);
                        })->orWhere('id', $offer->candidate_id);
                    })
                    ->select('id', 'first_name', 'last_name')
                    ->get()
                    ->map(function ($candidate) {
                        return [
                            'id' => $candidate->id,
                            'name' => $candidate->first_name . ' ' . $candidate->last_name
                        ];
                    }),
                'users' => User::emp()->where('created_by', creatorId())->select('id', 'name')->get(),
                'departments' => Department::where('created_by', creatorId())->select('id', 'department_name as name')->get(),
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreOfferRequest $request)
    {
        if (Auth::user()->can('create-offers')) {
            $validated = $request->validated();

            DB::transaction(function () use ($request, $validated) {
                // Get job_id from candidate's job application
                $candidate = Candidate::find($validated['candidate_id']);

                $offer = new Offer();
                $offer->candidate_id = $validated['candidate_id'];
                $offer->job_id = $candidate->job_id;
                $offer->offer_date = $validated['offer_date'];
                $offer->position = $validated['position'];
                $offer->department_id = $validated['department_id'];
                $offer->salary = $validated['salary'];
                $offer->bonus = $validated['bonus'];
                $offer->equity = $validated['equity'];
                $offer->benefits = $validated['benefits'];
                $offer->start_date = $validated['start_date'];
                $offer->expiration_date = $validated['expiration_date'];
                $offer->offer_letter_path = $validated['offer_letter_path'];
                $offer->status = $validated['status'];
                $offer->response_date = $validated['response_date'];
                $offer->decline_reason = $validated['decline_reason'];

                $offer->creator_id = Auth::id();
                $offer->created_by = creatorId();
                $offer->save();

                CreateOffer::dispatch($request, $offer);
            });

            return redirect()->route('recruitment.offers.index')->with('success', __('The offer has been created successfully.'));
        }
        else {
            return redirect()->route('recruitment.offers.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateOfferRequest $request, Offer $offer)
    {
        if (Auth::user()->can('edit-offers') && $this->assertBelongsToTenant($offer)) {
            $validated = $request->validated();

            DB::transaction(function () use ($request, $offer, $validated) {
                // Get job_id from candidate's job application
                $candidate = Candidate::find($validated['candidate_id']);

                $offer->candidate_id = $validated['candidate_id'];
                $offer->job_id = $candidate->job_id;
                $offer->offer_date = $validated['offer_date'] ?? $offer->offer_date;
                $offer->position = $validated['position'];
                $offer->department_id = $validated['department_id'];
                $offer->salary = $validated['salary'];
                $offer->bonus = $validated['bonus'];
                $offer->equity = $validated['equity'];
                $offer->benefits = $validated['benefits'];
                $offer->start_date = $validated['start_date'];
                $offer->expiration_date = $validated['expiration_date'];
                $offer->offer_letter_path = $validated['offer_letter_path'] ?? $offer->offer_letter_path;
                $offer->status = $validated['status'] ?? $offer->status;
                $offer->response_date = $validated['response_date'] ?? $offer->response_date;
                $offer->decline_reason = $validated['decline_reason'] ?? $offer->decline_reason;

                $offer->save();

                UpdateOffer::dispatch($request, $offer);
            });

            return redirect()->back()->with('success', __('The offer details are updated successfully.'));
        } else {
            return redirect()->route('recruitment.offers.index')->with('error', __('Permission denied'));
        }
    }

    public function updateApprovalStatus(Request $request, Offer $offer)
    {
        if (Auth::user()->can('approve-offers') && $this->assertBelongsToTenant($offer)) {
            $action = $request->input('action');

            $message = DB::transaction(function () use ($request, $offer, $action) {
                if ($action === 'approve') {
                    $offer->approved_by = Auth::id();
                    $offer->approval_status = 'approved';
                    $message = __('The offer has been approved successfully.');
                } else {
                    $offer->approved_by = null;
                    $offer->approval_status = 'rejected';
                    $message = __('The offer has been rejected successfully.');
                }

                $offer->save();

                UpdateOfferApprovalStatus::dispatch($request, $offer);

                return $message;
            });

            return redirect()->back()->with('success', $message);
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(Offer $offer)
    {
        if (Auth::user()->can('delete-offers') && $this->assertBelongsToTenant($offer)) {
            DB::transaction(function () use ($offer) {
                DestroyOffer::dispatch($offer);

                $offer->delete();
            });

            return redirect()->back()->with('success', __('The offer has been deleted.'));
        } else {
            return redirect()->route('recruitment.offers.index')->with('error', __('Permission denied'));
        }
    }

    public function downloadOfferLetter($encryptedId)
    {
        if (Auth::user()->can('download-offer-letters')) {
            try {
                $offerId = Crypt::decrypt($encryptedId);
                $offer = Offer::with(['candidate', 'job', 'department', 'approvedBy'])->findOrFail($offerId);

                $companySettings = getCompanyAllSetting();
                $companyName = $companySettings['company_name'] ?? config('app.name');

                // Get company default language
                $defaultLanguage = company_setting('defaultLanguage', creatorId()) ?? 'en';

                // Get offer letter template in company's default language
                $offerLetter = OfferLetter::where('lang', $defaultLanguage)
                    ->where('created_by', creatorId())
                    ->first();

                // Fallback to English if default language template not found
                if (!$offerLetter) {
                    $offerLetter = OfferLetter::where('lang', 'en')
                        ->where('created_by', creatorId())
                        ->first();
                }

                $templateContent = '';
                if ($offerLetter) {
                    // Prepare data for variable replacement
                    $workplaceLocation = 'Office';
                    if ($offer->job && $offer->job->location) {
                        $location = is_string($offer->job->location) ? json_decode($offer->job->location, true) : $offer->job->location;
                        if (is_array($location) && isset($location['address'])) {
                            $workplaceLocation = $location['address'] . ', ' . ($location['city'] ?? '') . ', ' . ($location['state'] ?? '');
                        } else {
                            $workplaceLocation = is_string($offer->job->location) ? $offer->job->location : 'Office';
                        }
                    }

                    $offerData = [
                        'applicant_name' => $offer->candidate->first_name . ' ' . $offer->candidate->last_name,
                        'company_name' => $companyName,
                        'job_title' => $offer->position ?? 'N/A',
                        'job_type' => $offer->job->job_type ?? 'Full-time',
                        'start_date' => $offer->start_date ?? 'TBD',
                        'workplace_location' => $workplaceLocation,
                        'days_of_week' => 'Monday to Friday',
                        'salary' => $offer->salary ? '$' . number_format($offer->salary) : 'N/A',
                        'salary_type' => 'year',
                        'salary_duration' => 'monthly',
                        'next_pay_period' => 'monthly',
                        'offer_expiration_date' => $offer->expiration_date ?? 'N/A',
                    ];

                    $templateContent = OfferLetter::replaceVariable($offerLetter->content, $offerData);
                }

                return Inertia::render('Recruitment/Offers/OfferLetterPdf', [
                    'offer' => $offer,
                    'companyName' => $companyName,
                    'companySettings' => $companySettings,
                    'templateContent' => $templateContent
                ]);
            } catch (\Exception $e) {
                return response('Invalid offer letter link.', 404);
            }
        } else {
            return response('Permission denied.', 403);
        }
    }

    public function sendEmail(Request $request, Offer $offer)
    {
        if (Auth::user()->can('send-offer-emails')) {
            try {
                $candidate = $offer->candidate;
                if (!$candidate || !$candidate->email) {
                    return redirect()->back()->with('error', __('Candidate email not found'));
                }

                $emailData = [
                    'candidate_name' => $candidate->first_name . ' ' . $candidate->last_name,
                    'position' => $offer->position,
                    'salary' => $offer->salary ? '$' . number_format($offer->salary) : 'To be discussed',
                    'start_date' => $offer->start_date,
                    'company_name' => getCompanyAllSetting()['company_name'] ?? config('app.name'),
                    'download_url' => route('recruitment.offers.public-download', Crypt::encrypt($offer->id)),
                ];

                $result = EmailTemplate::sendEmailTemplate('Offer Letter', [$candidate->email], $emailData, $offer->created_by);

                if ($result['is_success']) {
                    return redirect()->back()->with('success', __('Email sent successfully'));
                } else {
                    return redirect()->back()->with('error', __($result['error']));
                }
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __('Failed to send email'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function showConvertToEmployeeForm($id)
    {
        if (Auth::user()->can('convert-offers-to-employees')) {
            $offer = Offer::with('candidate')
                ->where('id', $id)
                ->where('created_by', creatorId())
                ->firstOrFail();

            if ($offer->converted_to_employee) {
                return redirect()->back()->with('error', __('Offer already converted to employee'));
            }

            $hrmService = app(RecruitmentHrmIntegrationService::class);

            return Inertia::render('Recruitment/Offers/ConvertToEmployee', [
                'offer' => $offer,
                'branches' => $hrmService->getBranches(),
                'departments' => $hrmService->getDepartments(),
                'designations' => $hrmService->getDesignations(),
                'shifts' => $hrmService->getShifts(),
                'documentTypes' => $hrmService->getDocumentTypes(),
                'generatedEmployeeId' => $hrmService->generateEmployeeId(),
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function convertToEmployee(ConvertToEmployeeRequest $request, $id)
    {
        if (Auth::user()->can('convert-offers-to-employees')) {
            try {
                $offer = Offer::with('candidate')
                    ->where('id', $id)
                    ->where('created_by', creatorId())
                    ->firstOrFail();

                app(RecruitmentHrmIntegrationService::class)->convertOfferToEmployee($request, $offer);

                return redirect()->route('recruitment.offers.index')->with('success', __('Offer converted to employee successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->withErrors(['error' => __('Failed to convert offer to employee: ' . $e->getMessage())]);
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function getJobsByCandidate($candidateId)
    {
        if (Auth::user()->can('manage-job-postings') || Auth::user()->can('create-offers') || Auth::user()->can('edit-offers')) {
            $jobs = Job::where('candidate_id', $candidateId)
                ->where('created_by', creatorId())
                ->select('id', 'name')
                ->get();

            return response()->json($jobs);
        } else {
            return response()->json([], 403);
        }
    }
}
