<?php
/**
 * @author Jörn Friedrich Dreyer
 * @copyright (c) 2014 Jörn Friedrich Dreyer <jfd@owncloud.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Files\ObjectStore;

use OC\Files\Cache\CacheEntry;
use OC\Files\ObjectStore\NoopScanner;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OCP\Files\NotFoundException;
use OCP\Files\ObjectStore\IObjectStore;
use OCP\Files\ObjectStore\IVersionedObjectStorage;
use Test\TestCase;

/**
 * Class ObjectStoreTest
 *
 * @group DB
 *
 * @package Test\Files\ObjectStore
 */
class ObjectStoreTest extends TestCase {

	/** @var IObjectStore | IVersionedObjectStorage | \PHPUnit_Framework_MockObject_MockObject */
	private $impl;
	/** @var ObjectStoreStorage | \PHPUnit_Framework_MockObject_MockObject */
	private $objectStore;

	public function setUp() {
		parent::setUp();
		$this->impl = $this->createMock([IObjectStore::class, IVersionedObjectStorage::class]);
		$this->impl->expects($this->any())
			->method('getStorageId')
			->willReturn('object-store-test');
		$this->objectStore = new ObjectStoreStorage([
			'objectstore' => $this->impl
		]);
	}

	public function testMkDir() {
		$this->assertTrue($this->objectStore->mkdir('test'));
		$cacheData = $this->objectStore->getCache()->get('test');
		$this->assertInstanceOf(CacheEntry::class, $cacheData);
		$this->assertEquals('test', $cacheData->getPath());
		$this->assertEquals('httpd/unix-directory', $cacheData->getMimeType());
	}

	/**
	 * @depends testMkDir
	 */
	public function testRmdir() {
		$this->assertTrue($this->objectStore->rmdir('test'));
		$this->assertFalse($this->objectStore->file_exists('test'));
	}

	public function testGetAndPutContents() {
		$this->impl->expects($this->once())->method('writeObject');
		$this->assertEquals(5, $this->objectStore->file_put_contents('test.txt', 'lorem'));

		$stream = $this->createStreamFor('123456');

		$this->impl->expects($this->once())->method('readObject')->willReturn($stream);
		$this->assertEquals('123456', $this->objectStore->file_get_contents('test.txt'));
	}

	public function testGetScanner() {
		$this->assertInstanceOf(NoopScanner::class, $this->objectStore->getScanner());
	}

	public function testUnlink() {
		$this->impl->expects($this->once())->method('writeObject');
		$this->assertTrue($this->objectStore->touch('to-unlink.txt'));

		$this->impl->expects($this->once())->method('deleteObject');
		$this->assertTrue($this->objectStore->unlink('to-unlink.txt'));
	}

	/**
	 * @dataProvider providersPathAndMime
	 * @param string $expectedMime
	 * @param string $path
	 */
	public function testGetMime($expectedMime, $path) {
		$this->assertTrue($this->objectStore->touch($path));
		$this->assertEquals($expectedMime, $this->objectStore->getMimeType($path));

	}

	public function providersPathAndMime() {
		return [
			['image/jpeg', 'sample.jpeg'],
		];
	}

	public function testRename() {
		$this->assertTrue($this->objectStore->touch('alice.txt'));
		$this->assertTrue($this->objectStore->rename('alice.txt', 'bob.txt'));
		$this->assertFalse($this->objectStore->file_exists('alice.txt'));
		$this->assertTrue($this->objectStore->file_exists('bob.txt'));
	}

	/**
	 * @dataProvider providesMethods
	 * @expectedException \OCP\Files\NotFoundException
	 */
	public function testGetVersionsOfUnknownFile($method, $ignore = false) {
		if ($ignore) {
			throw new NotFoundException();
		}
		$this->impl->expects($this->never())->method($method)->willReturn([]);
		$this->assertEquals([], $this->objectStore->$method('unknown-file.txt', '1'));
	}

	/**
	 * @dataProvider providesMethods
	 */
	public function testGetVersions($method) {
		$path = 'file-with-versions.txt';
		$this->assertTrue($this->objectStore->touch($path));
		$this->impl->expects($this->once())->method($method)->willReturn([]);
		$this->assertEquals([], $this->objectStore->$method($path, '1'));
	}

	public function providesMethods() {
		return [
			'saveVersion' => ['saveVersion', true],
			'getVersions' => ['getVersions'],
			'getVersion' => ['getVersion'],
			'getContentOfVersion' => ['getContentOfVersion'],
			'restoreVersion' => ['restoreVersion'],
		];
	}
}
