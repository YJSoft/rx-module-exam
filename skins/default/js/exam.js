jQuery(function($) {
    // 카테고리 박스에서 하위분류가 없을대 처리
    if($('.exam_top_category').length) {
        var $category = $('.exam_top_category');
        if(!$category.find('> ul > li.on > ul').length) {
            $category.removeClass('sub_type');
        }
    }
    // 객관식 문제에서 답 클릭시 입력과함께 표시
    $('.qanswer_list a.ans_check').click(function(event) {
        event.preventDefault();
        var chk_val = $(this).attr('data-value').split(',');

        var $answer = $('#answer'+chk_val[0]);
        if($answer.length < 1) return;
        if(chk_val[2]=='Y') {
            var _ans_list = ($answer.val())? $answer.val().split(',') : new Array();
            var idx = _ans_list.indexOf(chk_val[1]);
            if(idx==-1) {
                _ans_list.push(chk_val[1]);
                $answer.parent().find('li').eq(chk_val[1]-1).find('.marking').addClass('show');
            } else {
                _ans_list.splice(idx,1);
                $answer.parent().find('li').eq(chk_val[1]-1).find('.marking').removeClass('show');
            }
            $answer.val(_ans_list.join(','));
        } else {
            $answer.val(chk_val[1]);
            $answer.parent().find('.marking').removeClass('show');
            $answer.parent().find('li').eq(chk_val[1]-1).find('.marking').addClass('show');
        }
    });
    $('a.exam_manager').click(function(event) {
        event.preventDefault();
        $('.exam_manager_group').removeClass('on');
        $(this).parent().toggleClass('on');
    });
});


