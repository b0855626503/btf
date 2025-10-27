<div class="btn-group btn-group-sm">
    <button type="button" class="btn btn-primary  dropdown-toggle dropdown-icon dropdown-toggle-split"
            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-cog"></i> <span class="sr-only">Toggle Dropdown</span>
    </button>
    <div class="dropdown-menu" role="menu">

        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="javascript:void(0)" onclick="editModal({{ $code }})">แก้ไขข้อมูล</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="javascript:void(0)" onclick="delModal({{ $code }})">ลบข้อมูล</a>
    </div>
</div>