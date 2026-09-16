<?php

function record_audit_event(string $action, string $detail, array $context = []): void
{
    try {
        $timestamp = date('c');
        $eventId = date('YmdHis') . '_' . bin2hex(random_bytes(4));

        firestore_write_document('auditLog', $eventId, [
            'action' => $action,
            'detail' => $detail,
            'actorUid' => $_SESSION['uid'] ?? null,
            'actorName' => $_SESSION['name'] ?? 'System',
            'createdAt' => $timestamp,
            'context' => $context,
        ]);
    } catch (Throwable $e) {
        error_log('Audit event could not be recorded: ' . $e->getMessage());
    }
}