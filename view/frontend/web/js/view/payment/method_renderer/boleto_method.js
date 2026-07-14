define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Ui/js/model/messageList'
    ],
    function (Component, $, validators, messageList) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Paghiper_Magento2/payment/boleto',
                cpfCnpj: ''
            },

            initObservable: function () {
                this._super()
                    .observe([
                        'cpfCnpj'
                    ]);

                this.cpfCnpj.subscribe(function (value) {
                    if (!value) return;
                    
                    var cleanValue = value.replace(/\D/g, '');
                    var maskedValue = '';

                    if (cleanValue.length <= 11) {
                        maskedValue = cleanValue.replace(/(\d{3})(\d)/, '$1.$2')
                                                .replace(/(\d{3})(\d)/, '$1.$2')
                                                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    } else {
                        maskedValue = cleanValue.substring(0, 14)
                                                .replace(/^(\d{2})(\d)/, '$1.$2')
                                                .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                                                .replace(/\.(\d{3})(\d)/, '.$1/$2')
                                                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                    }

                    if (value !== maskedValue) {
                        this.cpfCnpj(maskedValue);
                    }
                }.bind(this));

                return this;
            },

            validate: function () {
                // 1. Executa a validação do componente pai (Valida o Endereço de Cobrança se estiver visível)
                if (!this._super()) {
                    return false;
                }

                var value = this.cpfCnpj().replace(/[^\d]+/g, '');
                if (!value) {
                    messageList.addErrorMessage({ message: "O CPF/CNPJ é obrigatório." });
                    return false;
                }

                if (value.length !== 11 && value.length !== 14) {
                    messageList.addErrorMessage({ message: "O CPF/CNPJ deve conter 11 ou 14 dígitos." });
                    return false;
                }

                function validarCPF(cpf) {
                    if (/^(\d)\1+$/.test(cpf)) return false;
                    var soma = 0, resto;
                    for (var i = 1; i <= 9; i++) soma = soma + parseInt(cpf.substring(i - 1, i)) * (11 - i);
                    resto = (soma * 10) % 11;
                    if ((resto == 10) || (resto == 11)) resto = 0;
                    if (resto != parseInt(cpf.substring(9, 10))) return false;
                    soma = 0;
                    for (var i = 1; i <= 10; i++) soma = soma + parseInt(cpf.substring(i - 1, i)) * (12 - i);
                    resto = (soma * 10) % 11;
                    if ((resto == 10) || (resto == 11)) resto = 0;
                    if (resto != parseInt(cpf.substring(10, 11))) return false;
                    return true;
                }

                function validarCNPJ(cnpj) {
                    if (/^(\d)\1+$/.test(cnpj)) return false;
                    var tamanho = cnpj.length - 2;
                    var numeros = cnpj.substring(0, tamanho);
                    var digitos = cnpj.substring(tamanho);
                    var soma = 0;
                    var pos = tamanho - 7;
                    for (var i = tamanho; i >= 1; i--) {
                        soma += numeros.charAt(tamanho - i) * pos--;
                        if (pos < 2) pos = 9;
                    }
                    var resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
                    if (resultado !== parseInt(digitos.charAt(0))) return false;

                    tamanho = tamanho + 1;
                    numeros = cnpj.substring(0, tamanho);
                    soma = 0;
                    pos = tamanho - 7;
                    for (i = tamanho; i >= 1; i--) {
                        soma += numeros.charAt(tamanho - i) * pos--;
                        if (pos < 2) pos = 9;
                    }
                    resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
                    return resultado === parseInt(digitos.charAt(1));
                }

                var isValid = value.length <= 11 ? validarCPF(value) : validarCNPJ(value);
                if (!isValid) {
                    messageList.addErrorMessage({ message: "CPF/CNPJ inválido" });
                }

                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid') && isValid;
            },

            /**
             * Indexa o dado exatamente como o input name="payment[boleto_cpf]" espera
             */
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'boleto_cpf': this.cpfCnpj().replace(/[^\d]+/g, '')
                    }
                };
            },

            getCode: function () {
                return 'paghiper_boleto';
            }
        });
    }
);