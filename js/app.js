// app.js

// Registro do Service Worker para PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('../service-worker.js').then(function(registration) {
            console.log('ServiceWorker registration successful with scope: ', registration.scope);
        }, function(err) {
            console.log('ServiceWorker registration failed: ', err);
        });
    });
}

// Função para mostrar notificações
function showNotification(message, type = 'info') {
    const alertPlaceholder = document.getElementById('alertPlaceholder');
    const wrapper = document.createElement('div')
    wrapper.innerHTML = [
        `<div class="alert alert-${type} alert-dismissible" role="alert">`,
        `   <div>${message}</div>`,
        '   <button type="button" class="close" data-dismiss="alert" aria-label="Close">',
        '       <span aria-hidden="true">&times;</span>',
        '   </button>',
        '</div>'
    ].join('')

    alertPlaceholder.append(wrapper)
}

// Função para validar formulários
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (form.checkValidity() === false) {
        event.preventDefault();
        event.stopPropagation();
    }
    form.classList.add('was-validated');
}

// Event listeners
document.addEventListener('DOMContentLoaded', (event) => {
    // Adicionar validação a todos os formulários
    const forms = document.getElementsByTagName('form');
    for (let form of forms) {
        form.addEventListener('submit', function(event) {
            validateForm(this.id);
        }, false);
    }

    // Exemplo de uso da função de notificação
    // showNotification('Bem-vindo ao Sistema de Controle de Extintores!', 'success');
});