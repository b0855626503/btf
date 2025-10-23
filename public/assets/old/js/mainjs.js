function copylink(){
    $(".myAlert-top").show();
    setTimeout(function(){
        $(".myAlert-top").hide();
    }, 1500);
}

$(document).ready(function(){
    $(".copybtn").click(function(event){
        var $tempElement = $("<input>");
        $("body").append($tempElement);
        $tempElement.val($(this).closest(".copybtn").find("span").text()).select();
        document.execCommand("Copy");
        $tempElement.remove();

    });
});
