<?php

use App\Enums\LogbookStatus;

it('mengizinkan hanya transisi yang ditentukan', function (LogbookStatus $from, array $allowed) {
    expect($from->allowedTransitions())->toBe($allowed);

    foreach (LogbookStatus::cases() as $target) {
        expect($from->canTransitionTo($target))->toBe(in_array($target, $allowed, true));
    }
})->with([
    'draft' => [LogbookStatus::Draft, [LogbookStatus::Submitted]],
    'submitted' => [LogbookStatus::Submitted, [LogbookStatus::Approved, LogbookStatus::RevisionRequested, LogbookStatus::Rejected]],
    'revision_requested' => [LogbookStatus::RevisionRequested, [LogbookStatus::Submitted]],
    'approved' => [LogbookStatus::Approved, []],
    'rejected' => [LogbookStatus::Rejected, []],
]);

it('menandai status final hanya untuk approved dan rejected', function () {
    expect(LogbookStatus::Approved->isFinal())->toBeTrue()
        ->and(LogbookStatus::Rejected->isFinal())->toBeTrue()
        ->and(LogbookStatus::Draft->isFinal())->toBeFalse()
        ->and(LogbookStatus::Submitted->isFinal())->toBeFalse()
        ->and(LogbookStatus::RevisionRequested->isFinal())->toBeFalse();
});

it('hanya mengizinkan edit pada draft dan revision_requested', function () {
    $editable = collect(LogbookStatus::cases())->filter->isEditable()->values()->all();

    expect($editable)->toBe([LogbookStatus::Draft, LogbookStatus::RevisionRequested]);
});
