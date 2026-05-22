// Abrir ou criar banco de dados IndexedDB
let db;
let request = indexedDB.open('ControleExtintoresDB', 1);

request.onerror = function(event) {
    console.error("Erro ao abrir o banco de dados IndexedDB", event);
};

request.onsuccess = function(event) {
    db = event.target.result;
    console.log("Banco de dados IndexedDB aberto com sucesso.");
    window.addEventListener('online', syncData); // Sincronizar quando online
};

request.onupgradeneeded = function(event) {
    let db = event.target.result;
    let objectStore = db.createObjectStore("inspections", { keyPath: "id", autoIncrement: true });
    objectStore.createIndex("date", "date", { unique: false });
    objectStore.createIndex("status", "status", { unique: false });
    console.log("Object store 'inspections' criado com sucesso.");
};

// Função para salvar inspeção localmente
function saveInspectionOffline(inspectionData) {
    if (db) {
        let transaction = db.transaction(["inspections"], "readwrite");
        let objectStore = transaction.objectStore("inspections");
        let request = objectStore.add(inspectionData);

        request.onsuccess = function() {
            console.log("Inspeção salva localmente.");
        };

        request.onerror = function(event) {
            console.error("Erro ao salvar inspeção localmente:", event.target.errorCode);
        };
    } else {
        console.error("Banco de dados não está disponível.");
    }
}

// Chame esta função para iniciar o salvamento de uma inspeção
function initSaveInspection(codigo, Local_Exato, selo_do_Inmetro, sinalizacao_vertical, sinalizacao_piso, ficha_inspecao_trimestral, lacre, pressao_manometro, anel_identificacao, pesagem_co2_semestral, comentarios, foto_nome, username) {
    if (db) {
        saveInspectionOffline({
            codigo: codigo,
            Local_Exato: Local_Exato,
            selo_do_Inmetro: selo_do_Inmetro,
            sinalizacao_vertical: sinalizacao_vertical,
            sinalizacao_piso: sinalizacao_piso,
            ficha_inspecao_trimestral: ficha_inspecao_trimestral,
            lacre: lacre,
            pressao_manometro: pressao_manometro,
            anel_identificacao: anel_identificacao,
            pesagem_co2_semestral: pesagem_co2_semestral,
            comentarios: comentarios,
            foto: foto_nome,
            usuario: username,
            data: new Date().toISOString()
        });
    } else {
        console.error("Banco de dados não está disponível.");
    }
}

// Verificação de conexão e sincronização
function syncData() {
    if (navigator.onLine && db) {
        let transaction = db.transaction(["inspections"], "readwrite");
        let objectStore = transaction.objectStore("inspections");
        let request = objectStore.getAll();

        request.onsuccess = function(event) {
            let inspections = event.target.result;
            if (inspections.length > 0) {
                fetch('/salvar_inspecao.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(inspections)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Limpar dados locais após sincronização bem-sucedida
                        let clearTransaction = db.transaction(["inspections"], "readwrite");
                        let clearObjectStore = clearTransaction.objectStore("inspections");
                        clearObjectStore.clear();
                        console.log('Sincronização bem-sucedida e dados locais limpos.');
                    } else {
                        console.error('Falha ao sincronizar dados com o servidor.');
                    }
                })
                .catch(error => {
                    console.error('Erro na sincronização:', error);
                });
            }
        };
    }
}

// Verificar conexão ao carregar a página
window.addEventListener('online', syncData);

// Checar suporte ao IndexedDB
if (!window.indexedDB) {
    console.error("Seu navegador não suporta IndexedDB. Algumas funcionalidades podem não funcionar.");
}