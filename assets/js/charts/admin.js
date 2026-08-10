!(function (NioApp, $) {
    "use strict";


    var statusComparison = {
        labels : ["Active", "Free Trial", "Dormant"],
        dataUnit : 'Companies',
        legend: false,
        datasets : [{
            borderColor : "#fff",
            background : ["#0fac81","#FFBB00","#f1f3f5"],
            data: statusData
        }]
    };
    var planComparison = {
        labels : labels,
        dataUnit : 'Companies',
        legend: false,
        datasets : [{
            borderColor : "#fff",
            background : ["#f62d51", "#dddddd", "#ffbc34", "#7460ee", "#009efb", "#2f3d4a", "#90a4ae", "#55ce63","#FFBB00","#0fac81","#0971fe","#854fff"],
            data: amount
        }]
    };

    function doughnutS1(selector, set_data){
        var $selector = (selector) ? $(selector) : $('.statistics');
        $selector.each(function(){
            var $self = $(this), _self_id = $self.attr('id'), _get_data = (typeof set_data === 'undefined') ? eval(_self_id) : set_data;
            var selectCanvas = document.getElementById(_self_id).getContext("2d");

            var chart_data = [];
            for (var i = 0; i < _get_data.datasets.length; i++) {
                chart_data.push({
                    backgroundColor: _get_data.datasets[i].background,
                    borderWidth:2,
                    borderColor: _get_data.datasets[i].borderColor,
                    hoverBorderColor: _get_data.datasets[i].borderColor,
                    data: _get_data.datasets[i].data,
                });
            } 
            var chart = new Chart(selectCanvas, {
                type: 'doughnut',
                data: {
                    labels: _get_data.labels,
                    datasets: chart_data,
                },
                options: {
                    legend: {
                        display: (_get_data.legend) ? _get_data.legend : false,
                        labels: {
                            boxWidth:12,
                            padding:20,
                            fontColor: '#6783b8',
                        }
                    },
                    rotation: -1.5,
                    cutoutPercentage:70,
                    maintainAspectRatio: false,
                    tooltips: {
                        enabled: true,
                        callbacks: {
                            title: function(tooltipItem, data) {
                                return data['labels'][tooltipItem[0]['index']];
                            },
                            label: function(tooltipItem, data) {
                                return data.datasets[tooltipItem.datasetIndex]['data'][tooltipItem['index']] + ' ' + _get_data.dataUnit;
                            }
                        },
                        backgroundColor: '#1c2b46',
                        titleFontSize: 13,
                        titleFontColor: '#fff',
                        titleMarginBottom: 6,
                        bodyFontColor: '#fff',
                        bodyFontSize: 12,
                        bodySpacing:4,
                        yPadding: 10,
                        xPadding: 10,
                        footerMarginTop: 0,
                        displayColors: false
                    },
                }
            });
        })
    }
    // init chart
    NioApp.coms.docReady.push(function(){ doughnutS1(); });  

})(NioApp, jQuery);