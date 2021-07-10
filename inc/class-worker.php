<?php
/**
 * Cavalcade Runner
 */

namespace HM\Cavalcade\Runner;

class Worker {
	public $process;
	public $pipes = [];
	public $job;

	public $output = '';
	public $error_output = '';
	public $status = null;

	public function __construct( $process, $pipes, Job $job ) {
		$this->process = $process;
		$this->pipes = $pipes;
		$this->job = $job;
	}

	public function is_done() {
		if ( isset( $this->status['running'] ) && ! $this->status['running'] ) {
			// Already exited, so don't try and fetch again
			// (Exit code is only valid the first time after it exits)
			return ! ( $this->status['running'] );
		}

		$this->status = proc_get_status( $this->process );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		// printf( '[%d] Worker status: %s' . PHP_EOL, $this->job->id, print_r( $this->status, true ) );
		return ! ( $this->status['running'] );
	}

	/**
	 * Drain stdout & stderr into properties.
	 *
	 * Draining the pipes is needed to avoid workers hanging when they hit the system pipe buffer limits.
	 */
	public function drain_pipes() {
		while ( $data = fread( $this->pipes[1], 1024 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->output .= $data;
		}

		while ( $data = fread( $this->pipes[2], 1024 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$this->error_output .= $data;
		}
	}

	/**
	 * Shut down the process
	 *
	 * @return bool Did the process run successfully?
	 */
	public function shutdown() {

		// Exhaust the streams
		$this->drain_pipes();
		fclose( $this->pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $this->pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		
		$job_return = array(
			"out" => $this->output, 
			"error_out" => $this->error_output, 
			"exitcode" => $this->status['exitcode']	
		);
		printf( '[%s][%d] Job Done %s' . PHP_EOL, $this->job->get_site_url(), $this->job->id, json_encode( $job_return) );

		// Close the process down too
		proc_close( $this->process );
		unset( $this->process );

		return ( $this->status['exitcode'] === 0 );
	}
}
