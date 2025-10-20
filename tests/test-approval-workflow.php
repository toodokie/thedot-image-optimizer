<?php

class Test_MSH_Approval_Workflow extends WP_UnitTestCase {

	public function setUp() : void {
		parent::setUp();

		// Ensure tables exist.
		MSH_Approval_Workflow::get_instance()->maybe_create_table();
	}

	public function test_multi_step_approval_chain_advances_reviewers() {
		$media_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$versioning = MSH_Metadata_Versioning::get_instance();
		$version_id = $versioning->save_version( $media_id, 'en_US', 'title', 'Chain Test', 'manual' );

		$reviewer_one = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$reviewer_two = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $reviewer_one );

		$workflow = MSH_Approval_Workflow::get_instance();
		$queue_id = $workflow->submit( $media_id, $version_id, array( $reviewer_one, $reviewer_two ), 'Initial submission for approval.' );
		$this->assertNotFalse( $queue_id );

		$item = $workflow->get_queue_item( $queue_id );
		$this->assertEquals( MSH_Approval_Workflow::STATUS_REVIEW, $item['status'] );
		$this->assertEquals( $reviewer_one, (int) $item['reviewer_id'] );

		$workflow->handle_action( $queue_id, 'approve', $reviewer_one, 'Looks good from my end.' );
		$item = $workflow->get_queue_item( $queue_id );
		$this->assertEquals( $reviewer_two, (int) $item['reviewer_id'] );
		$this->assertEquals( MSH_Approval_Workflow::STATUS_REVIEW, $item['status'] );

		$workflow->handle_action( $queue_id, 'approve', $reviewer_two, 'Approved for launch.' );
		$item = $workflow->get_queue_item( $queue_id );
		$this->assertEquals( MSH_Approval_Workflow::STATUS_APPROVED, $item['status'] );

		$approved_version = $versioning->get_version_by_id( $version_id );
		$this->assertEquals( $reviewer_two, (int) $approved_version['approved_by'] );
		$this->assertNotEmpty( $approved_version['approved_at'] );
	}

	public function test_request_changes_moves_to_changes_status() {
		$media_id = $this->factory->attachment->create_upload_object( __DIR__ . '/fixtures/test-image.jpg' );
		$version_id = MSH_Metadata_Versioning::get_instance()->save_version( $media_id, 'en_US', 'alt', 'Alt for review', 'manual' );
		$reviewer = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$workflow = MSH_Approval_Workflow::get_instance();
		$queue_id = $workflow->submit( $media_id, $version_id, array( $reviewer ) );
		$this->assertNotFalse( $queue_id );

		$workflow->handle_action( $queue_id, 'request_changes', $reviewer, 'Needs stronger call to action.' );
		$item = $workflow->get_queue_item( $queue_id );
		$this->assertEquals( MSH_Approval_Workflow::STATUS_CHANGES, $item['status'] );
		$this->assertEquals( $reviewer, (int) $item['reviewer_id'] );
	}
}

