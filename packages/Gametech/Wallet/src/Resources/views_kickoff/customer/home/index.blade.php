@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')


@section('content')
	
	<div class="sub-page sub-footer bg-member">
		<div class="container p-0">
			<div class="member-container">
				@if(isset($notice[Route::currentRouteName()]['route']) === true)
					
					<div class="marquee-text-container text-center mt-2">
						<div class="announcer d-inline-flex w-100">
							<div>
								<img src="/assets/kimberbet/images/icon/speaker.png">
							</div>
							<div style="flex: 1 1 0%; align-items: center; display: flex;">
								<marquee>
									<span style="margin-right: 3em;">{{ $notice[Route::currentRouteName()]['msg'] }}</span>
								</marquee>
							</div>
						</div>
					</div>
				
				@endif
				
				<member-credit ref="memberComponent"></member-credit>
				
				<member-menu ref="memberMenuComponent"
				             @open-deposit="VueApp.$refs.depositModalComponent.showModal()"
				             @open-withdraw="VueApp.$refs.withdrawModalComponent.showModal()"
				             @open-bonus="VueApp.$refs.bonusModalComponent.showModal()"
					@open-event="VueApp.$refs.eventModalComponent.showModal()">
				</member-menu>
				
				<page-slide></page-slide>
				
				
			
			</div>
		</div>
	</div>

@endsection




