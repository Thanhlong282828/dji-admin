<?php

use Illuminate\Support\Facades\Route;
use Modules\Recruitment\Presentation\Http\Controllers\CandidateOfferController;
use Modules\Recruitment\Presentation\Http\Controllers\InterviewController;
use Modules\Recruitment\Presentation\Http\Controllers\RecruitmentController;
use Modules\Recruitment\Presentation\Http\Controllers\RecruitmentExportController;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('recruitment/dashboard', [RecruitmentController::class, 'dashboard'])->middleware('permission:recruitment-requisition.view')->name('recruitment.dashboard');
    Route::get('recruitment-requisitions', [RecruitmentController::class, 'requisitions'])->middleware('permission:recruitment-requisition.view')->name('recruitment-requisitions.index');
    Route::post('recruitment-requisitions', [RecruitmentController::class, 'storeRequisition'])->middleware('permission:recruitment-requisition.create')->name('recruitment-requisitions.store');
    Route::post('recruitment-requisitions/{requisition}/{status}', [RecruitmentController::class, 'transition'])
        ->whereIn('status', ['approval', 'approved', 'rejected', 'draft', 'open', 'paused', 'filled', 'cancelled'])
        ->name('recruitment-requisitions.transition');
    Route::get('candidates', [RecruitmentController::class, 'candidates'])->middleware('permission:candidate.view')->name('candidates.index');
    Route::post('candidates', [RecruitmentController::class, 'storeCandidate'])->middleware('permission:candidate.create')->name('candidates.store');
    Route::delete('candidates/{candidate}', [RecruitmentController::class, 'archiveCandidate'])->middleware('permission:candidate.archive')->name('candidates.archive');
    Route::post('candidates/{candidate}/restore', [RecruitmentController::class, 'restoreCandidate'])->middleware('permission:candidate.restore')->name('candidates.restore');
    Route::post('candidates/{candidate}/merge', [RecruitmentController::class, 'mergeCandidates'])->middleware('permission:candidate.merge')->name('candidates.merge');
    Route::post('candidates/{candidate}/resume', [RecruitmentController::class, 'uploadCandidateResume'])->middleware('permission:candidate.upload-resume')->name('candidates.resume.store');
    Route::get('candidates/{candidate}/resume', [RecruitmentController::class, 'downloadCandidateResume'])->middleware('permission:candidate.download-resume')->name('candidates.resume.download');
    Route::delete('candidates/{candidate}/resume', [RecruitmentController::class, 'removeCandidateResume'])->middleware('permission:candidate.upload-resume')->name('candidates.resume.destroy');
    Route::get('applications/pipeline', [RecruitmentController::class, 'pipeline'])->middleware('permission:application.view')->name('applications.pipeline');
    Route::post('applications', [RecruitmentController::class, 'storeApplication'])->middleware('permission:application.create')->name('applications.store');
    Route::patch('applications/{application}/move-stage', [RecruitmentController::class, 'move'])->middleware('permission:application.change-stage')->name('applications.move-stage');
    Route::get('interviews', [InterviewController::class, 'index'])->middleware('permission:interview.view')->name('interviews.index');
    Route::get('interviews/create', [InterviewController::class, 'create'])->middleware('permission:interview.schedule')->name('interviews.create');
    Route::post('interviews', [InterviewController::class, 'store'])->middleware('permission:interview.schedule')->name('interviews.store');
    Route::get('interviews/{interview}', [InterviewController::class, 'show'])->middleware('permission:interview.view')->name('interviews.show');
    Route::patch('interviews/{interview}/{target}', [InterviewController::class, 'transition'])
        ->whereIn('target', ['confirmed', 'completed', 'cancelled', 'no_show'])
        ->middleware('permission:interview.view')
        ->name('interviews.transition');
    Route::get('interviews/{interview}/evaluation', [InterviewController::class, 'editEvaluation'])->middleware('permission:interview.evaluate')->name('interviews.evaluation.edit');
    Route::get('interviews/{interview}/panel-summary', [InterviewController::class, 'panelSummary'])->middleware('permission:interview.view-panel-summary')->name('interviews.panel-summary');
    Route::put('interviews/{interview}/evaluation/draft', [InterviewController::class, 'saveEvaluationDraft'])->middleware('permission:interview.evaluate')->name('interviews.evaluation.draft');
    Route::put('interviews/{interview}/evaluation/submit', [InterviewController::class, 'submitEvaluation'])->middleware('permission:interview.evaluate')->name('interviews.evaluation.submit');
    Route::patch('interview-evaluations/{evaluation}/reopen', [InterviewController::class, 'reopenEvaluation'])->middleware('permission:interview.reopen-evaluation')->name('interviews.evaluation.reopen');
    Route::patch('interviews/{interview}/reschedule', [InterviewController::class, 'reschedule'])->middleware('permission:interview.reschedule')->name('interviews.reschedule');
    Route::post('interviews/{interview}/interviewers', [InterviewController::class, 'assignInterviewers'])->middleware('permission:interview.assign-interviewer')->name('interviews.interviewers.store');
    Route::delete('interviews/{interview}/interviewers/{user}', [InterviewController::class, 'removeInterviewer'])->middleware('permission:interview.assign-interviewer')->name('interviews.interviewers.destroy');
    Route::get('candidate-offers', [CandidateOfferController::class, 'index'])->middleware('permission:candidate-offer.view')->name('candidate-offers.index');
    Route::get('candidate-offers/create', [CandidateOfferController::class, 'create'])->middleware('permission:candidate-offer.create')->name('candidate-offers.create');
    Route::post('candidate-offers', [CandidateOfferController::class, 'store'])->middleware('permission:candidate-offer.create')->name('candidate-offers.store');
    Route::get('candidate-offers/{candidate_offer}/hire', [CandidateOfferController::class, 'hireForm'])->middleware('permission:candidate-offer.hire')->name('candidate-offers.hire-form');
    Route::post('candidate-offers/{candidate_offer}/hire', [CandidateOfferController::class, 'hire'])->middleware('permission:candidate-offer.hire')->name('candidate-offers.hire');
    Route::get('candidate-offers/{candidate_offer}', [CandidateOfferController::class, 'show'])->middleware('permission:candidate-offer.view')->name('candidate-offers.show');
    Route::patch('candidate-offers/{candidate_offer}/{target}', [CandidateOfferController::class, 'transition'])
        ->whereIn('target', ['pending_approval', 'approved', 'draft', 'sent', 'accepted', 'declined', 'withdrawn', 'expired'])
        ->middleware('permission:candidate-offer.view')
        ->name('candidate-offers.transition');
    Route::post('candidate-offers/{candidate_offer}/document', [CandidateOfferController::class, 'uploadDocument'])->middleware('permission:candidate-offer.update')->name('candidate-offers.document.store');
    Route::get('candidate-offers/{candidate_offer}/download', [CandidateOfferController::class, 'downloadDocument'])->middleware('permission:candidate-offer.download')->name('candidate-offers.download');
    Route::delete('candidate-offers/{candidate_offer}/document', [CandidateOfferController::class, 'removeDocument'])->middleware('permission:candidate-offer.update')->name('candidate-offers.document.destroy');
    Route::get('interviews-export.csv', [RecruitmentExportController::class, 'interviews'])->middleware('permission:interview.view')->name('interviews.export');
    Route::get('candidate-offers-export.csv', [RecruitmentExportController::class, 'offers'])->middleware('permission:candidate-offer.view')->name('candidate-offers.export');
});
