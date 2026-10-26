<?php
// Helper Functions - Toan
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}
function get_current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}
function redirect(string $url): void {
    header("Location: $url");
    exit;
}
