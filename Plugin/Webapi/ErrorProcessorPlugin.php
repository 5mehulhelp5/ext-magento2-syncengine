<?php

namespace SyncEngine\Connector\Plugin\Webapi;

class ErrorProcessorPlugin
{
    /**
     * @param  \Magento\Framework\Webapi\ErrorProcessor  $subject
     * @param  \Exception      $masked
     * @param  \Exception      $original
     *
     * @return \Exception|\Magento\Framework\Webapi\Exception
     */
    public function afterMaskException( $subject, $masked, $original ) {
        $maskedMessage = $masked->getMessage();

        if ( $this->isGenericMessage( $maskedMessage ) && $masked->getHttpCode() >= 500 ) {
            $safeMessage = $this->sanitizeMessage( $original->getMessage() );

            // Insert safe message after "Internal Error." or "Server internal error."
            $newMessage = $maskedMessage . ' | SyncEngine debug: ' . $safeMessage;

            return new \Magento\Framework\Webapi\Exception(
                __( $newMessage ),
                $masked->getHttpCode(),
                $masked->getErrors(),
                $masked->getHttpCode()
            );
        }

        return $masked;
    }

    private function isGenericMessage( string $message ): bool
    {
        $normalized = strtolower( $message );

        return (
            str_contains( $normalized, 'internal error' )
            || str_contains( $normalized, 'report id' )
            || str_contains( $normalized, 'see exception log for details' ) );
    }

    private function sanitizeMessage( string $message ): string
    {
        // Remove any absolute paths (Unix & Windows)
        // Example: /var/www/html/file.php or C:\xampp\htdocs\file.php
        $message = preg_replace( '#(/[^ ]+)+|[A-Z]:\\\\[^\s]+#i', '[path removed]', $message );
        return trim( $message );
    }
}
