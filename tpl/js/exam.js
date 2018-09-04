/* search */
function completeSearch(ret_obj, response_tags, params, fo_obj)
{
	fo_obj.submit();
}
/**
 * @brief 팝업으로 띄우기
 **/
function examopen(url, target) {
	if(typeof(target) == "undefined") target = "_blank";
	if(typeof(xeVid)!='undefined' && url.indexOf(request_uri)>-1 && !url.getQuery('vid')) url = url.setQuery('vid',xeVid);
	winopen(url, target, "width=910,height=700,scrollbars=yes,resizable=yes,toolbars=no");
}
/**
 * @brief 문제 유효성 검사
 **/
jQuery(function($) {
    $('#examJoin').submit(function(event) {
        var valid_check = ($("input[name=valid_check]").val()=='N')? 'N' : 'Y';
        var total_cnt = $('#total_q_count').text();
        if(total_cnt)
        {
            for(var i=1;i<=total_cnt;i++)
            {
                var obj = $('#answer'+i);
                if(!obj.length) continue;
                if(valid_check=='N')
                {
                    if(!obj.val()) $('#answer'+i).val('　');
                } else {
                    if(obj.attr('type')=="text")
                    {
                        if(!obj.val())
                        {
                            alert(i+msg_not_answer_text);
                            obj.focus();
                            return false;
                        }
                    } else if(obj.attr('type')=="hidden")
                    {
                        if(!obj.val())
                        {
                            alert(i+msg_not_answer_radio);
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    });
});