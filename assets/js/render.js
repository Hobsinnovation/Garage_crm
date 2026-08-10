/*!
 * Signer
 * Version 1.0 - built Sat, Oct 6th 2018, 01:12 pm
 * https://simcycreative.com
 * Simcy Creative - <hello@simcycreative.com>
 * Private License
 */

 (function(NioApp, $) {
"use strict";


var pdfDoc = null,
    pageNum = 1,
    pageRendering = false,
    pageNumPending = null,
    password = null,
    canvas = document.getElementById('document-viewer'),
    ctx = canvas.getContext('2d');

if ($(window).width() > 414) {
    var scale = 1.1;
}else{
    var scale = 0.6;
}

/**
 * Get page info from document, resize canvas accordingly, and render page.
 * @param num Page number.
 */
NioApp.renderPage = function(num) {
    $(".document-load").show();
    $(".signer-element").hide();
    pageRendering = true;
    // Using promise to fetch the page
    pdfDoc.getPage(num).then(function(page) {
        var viewport = page.getViewport($(".document-map").width() / page.getViewport(scale).width);
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        var renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };
        var renderTask = page.render(renderContext);

        // Wait for rendering to finish
        renderTask.promise.then(function() {
            $(".document-load").hide();
            $("[page="+pageNum+"]").show();

            if (pageNum == pdfDoc.numPages) {
                $("#next").addClass("disabled");
            } else {
                $("#next").removeClass("disabled");
            }

            if (pageNum == 1) {
                $("#prev").addClass("disabled");
            } else {
                $("#prev").removeClass("disabled");
            }

            pageRendering = false;
            if (pageNumPending !== null) {
                // New page rendering is pending
                NioApp.renderPage(pageNumPending);
                pageNumPending = null;
            }
        });
    });

    // Update page counters
    $("#page_num").text(num);
    pageNum = num;

}



/**
 * If another page rendering in progress, waits until the rendering is
 * finised. Otherwise, executes rendering immediately.
 */
NioApp.queueRenderPage = function(num) {
  if (pageRendering) {
    pageNumPending = num;
  } else {
    NioApp.renderPage(num);
  }
}

/**
 * Displays previous page.
 */
NioApp.onPrevPage = function() {
  if (pageNum <= 1) {
    return;
  }
  pageNum--;
  NioApp.queueRenderPage(pageNum);
}

$("body").on("click", "#prev", function(event){
    event.preventDefault();
    NioApp.onPrevPage();
});
// document.getElementById('prev').addEventListener('click', onPrevPage);

/**
 * Displays next page.
 */
NioApp.onNextPage = function() {
  if (pageNum >= pdfDoc.numPages) {
    return;
  }
  pageNum++;
  NioApp.queueRenderPage(pageNum);
}
$("body").on("click", "#next", function(event){
    event.preventDefault();
    NioApp.onNextPage();
});
// document.getElementById('next').addEventListener('click', );


/**
 * Asynchronously downloads PDF.
 */

NioApp.openDocument = function(url) {
    $(".document-load").show();
    $(".document-error").hide();

    // Fetch the endpoint ourselves first. This guarantees same-origin session
    // cookies are sent and lets us reject HTML/PHP errors before PDF.js sees
    // them as a corrupt PDF.
    fetch(url, {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
        headers: { "Accept": "application/pdf" }
    }).then(function(response) {
        return response.arrayBuffer().then(function(buffer) {
            var bytes = new Uint8Array(buffer);
            var signature = "";
            for (var i = 0; i < Math.min(5, bytes.length); i++) {
                signature += String.fromCharCode(bytes[i]);
            }

            var contentType = (response.headers.get("content-type") || "").toLowerCase();
            if (!response.ok || signature !== "%PDF-" || contentType.indexOf("application/pdf") === -1) {
                var text = "";
                try {
                    if (typeof TextDecoder !== "undefined") {
                        text = new TextDecoder("utf-8").decode(bytes);
                    } else {
                        for (var j = 0; j < Math.min(bytes.length, 1200); j++) {
                            text += String.fromCharCode(bytes[j]);
                        }
                    }
                } catch (ignore) {}
                text = (text || ("HTTP " + response.status + " - PDF endpoint did not return a PDF."))
                    .replace(/<[^>]*>/g, " ")
                    .replace(/\s+/g, " ")
                    .trim();
                if (text.length > 600) { text = text.substring(0, 600) + "…"; }
                throw new Error(text);
            }

            return PDFJS.getDocument({ data: bytes });
        });
    }).then(function(pdfDoc_) {
        pdfDoc = pdfDoc_;
        document.getElementById('page_count').textContent = pdfDoc.numPages;
        NioApp.renderPage(pageNum);
        if (pdfDoc.numPages == 1) {
            $("#next, #prev").addClass("disabled");
        }
    }).catch(function(error) {
        $(".document-error").find(".error-message").text(error.message || "Unable to load PDF.");
        $(".document-load").hide();
        $(".document-error").show();
    });
}

NioApp.openDocument(pdfDocument);

/*
 * Zoom in and Zoom Out
 */
$("body").on("click", ".btn-zoom", function(event){
    if($(this).attr("zoom") === "plus"){
        scale = scale - 0.1;
    }else{
        if (scale > 0) {
            scale = scale + 0.1;
        }
    }

    if (scale == 1) {
        $("#document-viewer").css("max-width", "100%");
    }else{
        $("#document-viewer").width("auto");
    }

    NioApp.renderPage(pageNum);
});


 })(NioApp, jQuery);

