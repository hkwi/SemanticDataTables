<?php

namespace SMWDataTables\DataTables;

use MediaWiki\MediaWikiServices;
use MWException;
use Parser;
use RequestContext;
use SMW\Query\PrintRequest;
use SMW\Query\QueryResult;

final class RowFormatter {

	private $parser = null;
	private $cachedLinker = false;
	private bool $linkSubjectValues;
	private bool $linkOtherValues;

	public function __construct( ?string $link = null ) {
		global $smwgQDefaultLinking;

		$defaultLinking = isset( $smwgQDefaultLinking ) ? (string)$smwgQDefaultLinking : 'all';
		$this->linkSubjectValues = $defaultLinking !== 'none';
		$this->linkOtherValues = $defaultLinking === 'all';

		switch ( $link ) {
			case 'head':
			case 'subject':
				$this->linkSubjectValues = true;
				$this->linkOtherValues = false;
				break;
			case 'all':
				$this->linkSubjectValues = true;
				$this->linkOtherValues = true;
				break;
			case 'none':
				$this->linkSubjectValues = false;
				$this->linkOtherValues = false;
				break;
		}
	}

	/**
	 * @param PrintRequest[]|null $printRequests
	 */
	public function rows( QueryResult $queryResult, ?array $printRequests = null ): array {
		$printRequests ??= $queryResult->getPrintRequests();
		$rows = [];

		while ( $subject = $queryResult->getNext() ) {
			$row = [];

			foreach ( $subject as $index => $field ) {
				$printRequest = $printRequests[$index] ?? null;
				$row[] = $this->cell(
					$field,
					$printRequest,
					$this->isSubjectPrintout( $printRequest, $index )
				);
			}

			$rows[] = $row;
		}

		return $rows;
	}

	private function cell( $field, ?PrintRequest $printRequest, bool $isSubjectPrintout ): array {
		$template = $this->template( $printRequest );
		$displayValues = [];
		$plainValues = [];
		$linker = $this->linkerForPrintout( $isSubjectPrintout );

		while ( ( $dataValue = $field->getNextDataValue() ) !== false ) {
			$html = $dataValue->getShortText( SMW_OUTPUT_HTML, $linker );

			if ( $template !== '' ) {
				$html = $this->renderTemplate(
					$template,
					$dataValue->getShortText( SMW_OUTPUT_WIKI, $linker )
				);
			}

			$plainValues[] = $this->plainText( $html );
			$displayValues[] = $html === '' ? '&nbsp;' : $html;
		}

		$display = $displayValues ? implode( ', ', $displayValues ) : '&nbsp;';
		$plain = trim( implode( ', ', $plainValues ) );

		return [
			'display' => $display,
			'filter' => $plain,
			'sort' => $plain,
		];
	}

	private function isSubjectPrintout( ?PrintRequest $printRequest, int $index ): bool {
		if ( $printRequest === null ) {
			return $index === 0;
		}

		return $printRequest->getMode() === PrintRequest::PRINT_THIS;
	}

	private function linkerForPrintout( bool $isSubjectPrintout ) {
		if (
			( $isSubjectPrintout && !$this->linkSubjectValues ) ||
			( !$isSubjectPrintout && !$this->linkOtherValues )
		) {
			return null;
		}

		if ( $this->cachedLinker !== false ) {
			return $this->cachedLinker;
		}

		if ( function_exists( 'smwfGetLinker' ) ) {
			$this->cachedLinker = smwfGetLinker();
			return $this->cachedLinker;
		}

		if ( class_exists( \Linker::class ) ) {
			$this->cachedLinker = new \Linker();
			return $this->cachedLinker;
		}

		$linkerClass = \MediaWiki\Linker\Linker::class;
		if ( class_exists( $linkerClass ) ) {
			$this->cachedLinker = new $linkerClass();
			return $this->cachedLinker;
		}

		$this->cachedLinker = null;
		return null;
	}

	private function template( ?PrintRequest $printRequest ): string {
		if ( $printRequest === null ) {
			return '';
		}

		$parameters = $printRequest->getParameters();
		$template = $parameters['template'] ?? '';

		return is_string( $template ) ? trim( $template ) : '';
	}

	private function plainText( string $html ): string {
		return trim( html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	private function renderTemplate( string $template, string $value ): string {
		$expanded = $this->expandTemplate( $template, [ 1 => $value ] );

		return Parser::stripOuterParagraph(
			$this->parser()->recursiveTagParseFully( $expanded )
		);
	}

	private function parser() {
		if ( $this->parser !== null ) {
			return $this->parser;
		}

		$context = RequestContext::getMain();
		$output = $context->getOutput();
		$parser = MediaWikiServices::getInstance()->getParserFactory()->getInstance();
		$title = $output->getTitle();

		if ( $title !== null ) {
			$parser->setTitle( $title );
		}

		$parser->setOptions( $output->parserOptions() );

		if ( method_exists( $parser, 'setOutputType' ) && defined( 'Parser::OT_HTML' ) ) {
			$parser->setOutputType( Parser::OT_HTML );
		}

		$parser->clearState();
		$this->parser = $parser;

		return $this->parser;
	}

	/**
	 * @throws MWException
	 */
	private function expandTemplate( string $template, array $args ): string {
		$parser = $this->parser();
		$title = $this->templateTitle( $template );
		$titleText = $title->getText();
		$frame = $parser->getPreprocessor()->newFrame();

		if ( $frame->depth >= $parser->getOptions()->getMaxTemplateDepth() ) {
			throw new MWException( 'expandTemplate: template depth limit exceeded' );
		}

		if ( MediaWikiServices::getInstance()->getNamespaceInfo()->isNonincludable( $title->getNamespace() ) ) {
			throw new MWException( 'expandTemplate: template inclusion denied' );
		}

		[ $dom, $finalTitle ] = $parser->getTemplateDom( $title );
		if ( $dom === false ) {
			throw new MWException( "expandTemplate: template \"$titleText\" does not exist" );
		}

		if ( !$frame->loopCheck( $finalTitle ) ) {
			throw new MWException( 'expandTemplate: template loop detected' );
		}

		$fargs = $parser->getPreprocessor()->newPartNodeArray( $args );
		$newFrame = $frame->newChild( $fargs, $finalTitle );

		return $newFrame->expand( $dom );
	}

	private function templateTitle( string $template ) {
		$titleClass = class_exists( \MediaWiki\Title\Title::class )
			? \MediaWiki\Title\Title::class
			: 'Title';

		return $titleClass::makeTitle(
			NS_TEMPLATE,
			$titleClass::capitalize( trim( $template ), NS_TEMPLATE )
		);
	}
}
