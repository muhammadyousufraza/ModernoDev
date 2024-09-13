import { Icon } from '@wordpress/icons';
import { Tab } from '@headlessui/react';
import classNames from 'classnames';
import { CardContent } from '@assist/components/dashboard/CardContent';
import { CardsTitle } from '@assist/components/dashboard/CardsTitle';
import { useTours } from '@assist/hooks/useTours';
import { useTasksStore } from '@assist/state/tasks';
import { Bullet, Check } from '@assist/svg';

export const DesktopCards = ({ className, tasks, totalCompleted }) => {
	const { isCompleted } = useTasksStore();
	const { finishedTour } = useTours();

	return (
		<div
			data-test="assist-tasks-module"
			id="assist-tasks-module"
			className={classNames(
				className,
				'mb-6 h-full w-full rounded border border-gray-300 bg-white text-base',
			)}>
			{tasks && (
				<Tab.Group
					vertical
					as="div"
					className="flex h-[472px] min-h-96 grow flex-row-reverse justify-between">
					<Tab.List
						as="div"
						className="w-96 overflow-auto border-l border-gray-100">
						<CardsTitle totalCompleted={totalCompleted} total={tasks.length} />

						{tasks.map((task) => (
							<TabItem
								key={task.slug}
								task={task}
								isCompleted={
									task.type === 'tour'
										? finishedTour(task.slug) || isCompleted(task.slug)
										: isCompleted(task.slug)
								}
							/>
						))}
					</Tab.List>

					<Tab.Panels as="div" className="w-3/4">
						{tasks.map((task) => (
							<Tab.Panel
								key={task.slug}
								as="div"
								data-test="assist-task-card-wrapper"
								className="h-full">
								<CardContent task={task} />
							</Tab.Panel>
						))}
					</Tab.Panels>
				</Tab.Group>
			)}
		</div>
	);
};

const TabItem = ({ task, isCompleted }) => (
	<Tab as="div" data-test={`assist-task-${task.slug}`}>
		{({ selected }) => (
			<div
				className={classNames(
					'group flex w-full items-center justify-between border-b border-gray-300 py-4 pl-2 pr-4 text-sm hover:cursor-pointer hover:bg-gray-100',
					{
						'bg-gray-100 font-semibold': selected,
					},
				)}>
				<div className="flex w-full items-center">
					<Icon
						icon={isCompleted ? Check : Bullet}
						size={12}
						data-test={
							isCompleted ? 'completed-task-icon' : 'uncompleted-task-icon'
						}
						className={classNames('mx-2 flex-shrink-0', {
							'stroke-current text-design-main':
								(isCompleted && selected) || (isCompleted && !selected),
							'fill-current text-design-main': selected && !isCompleted,
							'text-center text-gray-400': !isCompleted && !selected,
						})}
					/>
					<span>{task?.sidebarTitle ?? task.title}</span>
				</div>
			</div>
		)}
	</Tab>
);
