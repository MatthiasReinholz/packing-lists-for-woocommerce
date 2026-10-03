<?php
/** Execute isolated behavioral suites without polluting WordPress test globals. */
final class PackingListsTest extends PHPUnit\Framework\TestCase {
	public function testBehaviorAndAuthorization(): void {
		$this->runSuite( 'behavior.php', 'Controller checks passed.' );
	}

	public function testRealPdfDeliveryAndCleanup(): void {
		$this->runSuite( 'pdf.php', 'Real PDF integration checks passed.' );
	}

	private function runSuite( string $script, string $marker ): void {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/' . $script ) . ' 2>&1', $output, $status );
		$text = implode( "\n", $output );
		self::assertSame( 0, $status, $text );
		self::assertStringContainsString( $marker, $text );
	}
}
