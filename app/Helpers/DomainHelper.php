<?php

namespace App\Helpers;

class DomainHelper
{
    /**
     * Obtiene la URL base usando el formato punycode para evitar problemas CORS
     * con dominios que contienen caracteres especiales
     */
    public static function getPunycodeOrigin($request = null)
    {
        $currentHost = $request ? $request->getHost() : request()->getHost();

        // Si el dominio actual contiene caracteres especiales o es el dominio IDN
        if ($currentHost === 'floresdejazmin.com' ||
            $currentHost === 'xn--floresdejazmnflorera-04bh.com' ||
            strpos($currentHost, 'flores') !== false) {

            // Usar siempre la versión punycode para Izipay
            return 'https://xn--floresdejazmnflorera-04bh.com';
        }

        // Para otros dominios (desarrollo, etc.), usar el dominio actual
        return $request ? $request->getSchemeAndHttpHost() : request()->getSchemeAndHttpHost();
    }

    /**
     * Genera URLs de retorno para Izipay usando el dominio punycode
     */
    public static function getIzipayReturnUrls($orderNumber = null)
    {
        $baseUrl = self::getPunycodeOrigin();

        if ($orderNumber) {
            return [
                'success' => $baseUrl . '/payment/return?status=success&order=' . $orderNumber,
                'error' => $baseUrl . '/payment/return?status=error&order=' . $orderNumber,
                'cancel' => $baseUrl . '/checkout'
            ];
        }

        // URLs genéricas para configuración de Izipay
        return [
            'success' => $baseUrl . '/checkout/success',
            'error' => $baseUrl . '/checkout/error',
            'cancel' => $baseUrl . '/checkout/cancel'
        ];
    }

    /**
     * Configura headers CORS específicos para el dominio punycode
     */
    public static function setCorsHeaders($response)
    {
        $punycodeOrigin = self::getPunycodeOrigin();

        $response->headers->set('Access-Control-Allow-Origin', $punycodeOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Max-Age', '3600');

        return $response;
    }
}
