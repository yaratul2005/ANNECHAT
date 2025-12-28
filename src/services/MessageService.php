<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Message;
use App\Models\ActivityLog;
use App\Models\OnlineStatus;

class MessageService {
    private Message $messageModel;
    private ActivityLog $activityLog;
    private OnlineStatus $onlineStatus;

    public function __construct() {
        $this->messageModel = new Message();
        $this->activityLog = new ActivityLog();
        $this->onlineStatus = new OnlineStatus();
    }

    public function send(int $senderId, int $recipientId, ?string $messageText = null, ?string $attachmentType = null, ?string $attachmentUrl = null, ?string $attachmentName = null, ?int $attachmentSize = null): array {
        // Validate message text if provided
        if ($messageText !== null && !empty(trim($messageText))) {
            $errors = ValidationService::validateMessage($messageText);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
        }
        
        // Must have either message text or attachment
        if (empty(trim($messageText ?? '')) && empty($attachmentUrl)) {
            return ['success' => false, 'errors' => ['Message text or attachment is required']];
        }

        // Prevent near-duplicate messages (e.g., duplicated requests) by checking recent inserts
        $duplicateId = $this->messageModel->findRecentDuplicate($senderId, $recipientId, $messageText, $attachmentUrl, 5);
        if ($duplicateId !== null) {
            $messageId = $duplicateId;
        } else {
            $messageId = $this->messageModel->create(
                $senderId, 
                $recipientId, 
                $messageText, 
                $attachmentType, 
                $attachmentUrl, 
                $attachmentName, 
                $attachmentSize
            );
        }

        // Update online status
        $this->onlineStatus->update($senderId, 'online');

        // Log activity
        $this->activityLog->create($senderId, 'message_sent', "Message sent to user ID: {$recipientId}", $this->getIpAddress(), $this->getUserAgent());

        $message = $this->messageModel->findById($messageId);
        return ['success' => true, 'message' => $message];
    }

    public function getConversation(int $userId1, int $userId2, int $limit = 50, int $offset = 0): array {
        $messages = $this->messageModel->getConversation($userId1, $userId2, $limit, $offset);
        
        // Mark as read
        $this->messageModel->markConversationAsRead($userId1, $userId2);

        return ['success' => true, 'messages' => $messages];
    }

    public function pollNewMessages(int $userId, ?int $lastMessageId = null): array {
        // Update online status
        $this->onlineStatus->update($userId, 'online');

        $messages = $this->messageModel->getNewMessages($userId, $lastMessageId);
        
        // Mark as read
        foreach ($messages as $message) {
            $this->messageModel->markAsRead($message['id'], $userId);
        }

        return ['success' => true, 'messages' => $messages];
    }

    public function delete(int $messageId, int $userId, bool $isAdmin = false): array {
        $message = $this->messageModel->findById($messageId);
        
        if (!$message) {
            return ['success' => false, 'errors' => ['Message not found']];
        }

        if (!$isAdmin && $message['sender_id'] != $userId) {
            return ['success' => false, 'errors' => ['You can only delete your own messages']];
        }

        $this->messageModel->delete($messageId, $userId);
        
        // Log activity
        $this->activityLog->create($userId, 'message_deleted', "Message deleted: ID {$messageId}", $this->getIpAddress(), $this->getUserAgent());

        return ['success' => true, 'message' => 'Message deleted successfully'];
    }

    private function getIpAddress(): ?string {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function getUserAgent(): ?string {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}

