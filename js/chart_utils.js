/**
 * Reusable utility to create or update a line chart using Chart.js.
 *
 * @param {string} canvasId - The ID of the canvas element.
 * @param {string} globalChartVarName - The window property name to store the chart instance.
 * @param {Object} dataMap - An object where keys are labels (e.g., dates) and values are data points.
 * @param {string} datasetLabel - The label for the dataset.
 * @param {string|null} tooltipFormat - Optional format for the x-axis tooltip (e.g., 'dd/MM/yyyy').
 */
function updateLineChart(canvasId, globalChartVarName, dataMap, datasetLabel, tooltipFormat = null) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    const labels = Object.keys(dataMap);
    const data = Object.values(dataMap);

    // Verificar se já existe um gráfico e se ele é uma instância do Chart
    if (window[globalChartVarName] && typeof window[globalChartVarName].destroy === 'function') {
        window[globalChartVarName].destroy();
    }

    const timeOptions = {
        unit: 'day'
    };

    if (tooltipFormat) {
        timeOptions.tooltipFormat = tooltipFormat;
    }

    // Criar um novo gráfico
    window[globalChartVarName] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: datasetLabel,
                data: data,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                x: {
                    type: 'time',
                    time: timeOptions,
                    title: {
                        display: true,
                        text: 'Data'
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantidade'
                    }
                }
            }
        }
    });
}
