/**
 * @author Mathias Matas Hennig <mathias@tezus.com.br>
 * @updated_for_magento_2.4.9
 */
define([
    'jquery',
    'Magento_Ui/js/model/messageList'
], function ($, messageList) {
    'use strict';

    return function (config, element) {
        // Vincula o evento de clique de forma segura e encapsulada ao elemento alvo
        $(element).on('click', function (e) {
            e.preventDefault();

            // Alvo do texto que será copiado (passado dinamicamente via data-target ou seletor padrão)
            var targetSelector = config.target || '#select-this';
            var $target = $(targetSelector);

            if ($target.length) {
                var textToCopy = $target.val() || $target.text();

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    // Utiliza a API moderna, assíncrona e segura de transferência
                    navigator.clipboard.writeText(textToCopy.trim())
                        .then(function () {
                            alert('Código copiado com sucesso para a área de transferência!');
                        })
                        .catch(function (err) {
                            console.error('Falha ao copiar texto: ', err);
                        });
                } else {
                    // Fallback seguro caso o ambiente (HTTP sem SSL em localhost) não tenha suporte à Clipboard API
                    $target.select();
                    try {
                        document.execCommand('copy');
                        alert('Código copiado com sucesso!');
                    } catch (err) {
                        alert('Não foi possível copiar o código automaticamente. Por favor, copie manualmente.');
                    }
                }
            }
        });
    };
});