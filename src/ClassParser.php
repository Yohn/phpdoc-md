<?php
namespace Clean\PhpDocMd;

use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlock\Tags\InvalidTag;
use ReflectionClass;
use ReflectionMethod;

/**
 * Parses class and method docblocks using phpDocumentor reflection.
 */
class ClassParser {
	/**
	 * @var ReflectionClass Class reflection instance.
	 */
	private ReflectionClass $reflection;

	/**
	 * @var DocBlockFactory Factory for creating DocBlocks.
	 */
	private DocBlockFactory $docBlockFactory;

	/**
	 * @param ReflectionClass $class Target class for parsing.
	 */
	public function __construct(ReflectionClass $class) {
		$this->reflection = $class;
		$this->docBlockFactory = DocBlockFactory::createInstance();
	}

	/**
	 * Returns the class short and long description.
	 *
	 * @return object
	 */
	public function getClassDescription(): object {
		$docblock = $this->docBlockFactory->create($this->reflection->getDocComment() ?: '/** */');
		return (object) [
			'short' => (string) $docblock->getSummary(),
			'long'  => (string) $docblock->getDescription(),
		];
	}

	/**
	 * Returns parent class name or null.
	 *
	 * @return string|null
	 */
	public function getParentClassName(): ?string {
		return ($p = $this->reflection->getParentClass()) ? $p->getName() : null;
	}

	/**
	 * Returns implemented interface names.
	 *
	 * @return array
	 */
	public function getInterfaces(): array {
		return $this->reflection->getInterfaceNames();
	}

	/**
	 * Returns public method details excluding inherited.
	 *
	 * @return array
	 */
	public function getMethodsDetails(): array {
		$methods = [];
		$parentClassMethods = $this->getInheritedMethods();

		foreach ($this->reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if (isset($parentClassMethods[$method->getName()])) {
				continue;
			}
			$methods[$method->getName()] = $this->getMethodDetails($method);
		}

		return $methods;
	}

	/**
	 * Parses method docblock for descriptions, params, return and throws.
	 *
	 * @param ReflectionMethod $method
	 * @return object
	 */
	private function getMethodDetails(ReflectionMethod $method): object {
		$docblock = $this->docBlockFactory->create($method->getDocComment() ?: '/** */');

		$data = [
			'shortDescription'     => null,
			'longDescription'      => null,
			'argumentsList'        => [],
			'argumentsDescription' => null,
			'returnValue'          => null,
			'throwsExceptions'     => null,
			'visibility'           => null,
		];

		if ($docblock->getSummary()) {
			$data['shortDescription'] = $docblock->getSummary();
			$data['longDescription'] = $docblock->getDescription();
			$data['argumentsList'] = $this->retrieveParams($docblock->getTagsByName('param'));
			$data['argumentsDescription'] = $this->retrieveParamsDescription($docblock->getTagsByName('param'));
			$data['returnValue'] = $this->retrieveTagData($docblock->getTagsByName('return'));
			$data['throwsExceptions'] = $this->retrieveTagData($docblock->getTagsByName('throws'));
			$data['visibility'] = join(
				'',
				[
					$method->isFinal() ? 'final ' : '',
					'public',
					$method->isStatic() ? ' static' : '',
				]
			);
		} else {
			$className = sprintf("%s::%s", $method->class, $method->name);
			$atlasdoc = new \Clean\PhpAtlas\ClassMethod($className);
			$data['shortDescription'] = $atlasdoc->getMethodShortDescription();
			$data['doclink'] = $atlasdoc->getMethodPHPDocLink();
		}

		return (object) $data;
	}

	/**
	 * Returns inherited public methods from parent class.
	 *
	 * @return array
	 */
	public function getInheritedMethods(): array {
		$methods = [];
		$parentClass = $this->reflection->getParentClass();
		if ($parentClass) {
			foreach ($parentClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
				$methods[$method->getName()] = $this->getMethodDetails($method);
			}
		}
		ksort($methods);
		return $methods;
	}

	/**
	 * Extracts tag description and type from @param, @return, @throws.
	 *
	 * @param array $params
	 * @return array
	 */
	private function retrieveTagData(array $params): array {
		$data = [];
		foreach ($params as $param) {
			$data[] = (object) [
				'desc' => $param->getDescription(),
				'type' => $param->getType(),
			];
		}
		return $data;
	}

	/**
	 * Returns simplified param list from @param tags.
	 *
	 * @param array $params
	 * @return array
	 */
	private function retrieveParams(array $params): array {
		$data = [];
		foreach ($params as $param) {
			if ($param instanceof InvalidTag) {
				continue;
			}
			$data[] = sprintf("%s $%s", $param->getType(), $param->getVariableName());
		}
		return $data;
	}

	/**
	 * Returns param details with name, type, and description.
	 *
	 * @param array $params
	 * @return array
	 */
	private function retrieveParamsDescription(array $params): array {
		$data = [];
		foreach ($params as $param) {
			if ($param instanceof InvalidTag) {
				continue;
			}
			$data[] = (object) [
				'name' => '$' . $param->getVariableName(),
				'desc' => $param->getDescription(),
				'type' => $param->getType(),
			];
		}
		return $data;
	}

	/**
	 * Returns manual PHP doc link for the method.
	 *
	 * @param ReflectionMethod $method
	 * @return string
	 */
	private function getPHPDocLink(ReflectionMethod $method): string {
		return strtolower(sprintf('https://secure.php.net/manual/en/%s.%s.php', $method->class, $method->name));
	}
}
